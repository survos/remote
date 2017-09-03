<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\SqsCommand;
use AppBundle\Exception\AssignmentExceptionInterface;
use AppBundle\Exception\PosseExceptionInterface;
use Survos\Client\SurvosClient;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExternalWeatherCommand extends SqsCommand
{
    /** @var array */
    private $services;

    /** @var string */
    private $fromQueueName;

    /** @var  FilesystemCache */
    private $cache;

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('remote:weather')
            ->setDescription('Process a queue dedicated to weather')
            ->setHelp("Reads from an SQS queue, looks up the weather, then pushes back to channel")
            ;

        $this->cache = new FilesystemCache();

    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @throws \Exception
     * @return int
     */
    protected function XXexecute(InputInterface $input, OutputInterface $output)
    {
        $this->services = [];
        $this->fromQueueName = $input->getArgument('queue-name');
        $output->writeln("Reading from $this->fromQueueName", true);
#        $this->toQueueName = $input->getArgument('to-queue');
        $this->processQueue($this->fromQueueName);
        return 0; // OK
    }


    protected function processMessage(array $data, array $message) : bool
    {
        $data = $this->validateMessage($data);
        $payload = $data['payload'];
        if ($this->input->getOption('verbose')) {
            dump($data, $payload);
        }
        $this->survosClient = $this->getClient($data['apiUrl'], $data['accessToken']);
        try {
            $answers = $this->processAssignment($data);
            if ($this->input->getOption('verbose')) {
                dump($answers);
            }
            if ($answers) {
                // $data['command'] = 'partial';
                $this->sendData(array_filter($data, function ($key) { return in_array($key, ['command', 'taskId','assignmentId','channelCode']);}, ARRAY_FILTER_USE_KEY),
                    $answers);
            }
        } catch (\Exception $e) {
            if ($e instanceof AssignmentExceptionInterface) {
                $this->output->writeln(
                    "Assignment #{$e->getAssignmentId()}. ".$e->getMessage()." data:".json_encode(
                        $e->getRelatedData(), JSON_PRETTY_PRINT
                    )
                );
            } elseif ($e instanceof PosseExceptionInterface) {
                $this->output->writeln($e->getMessage()." data:".json_encode($e->getRelatedData(), JSON_PRETTY_PRINT));
            } else {
                // needs sorting as it shouldn't happen
                $message = $e->getMessage();
                if (preg_match('/^\{/', $message)) {
                    $message = json_encode(json_decode($message), JSON_PRETTY_PRINT);
                }
                $this->output->writeln($message);
                throw $e;
            }
        }

//        return false;

        return true; // message is handled and can be deleted
    }

    /**
     * get weather data - store locally to not fetch in case
     *
     * @param string $zip
     * @param string $countryCode
     * @return array
     */
    private function getWeatherData($zip, $countryCode = 'US')
    {

        // @todo: replace with guzzle or even guzzleCache

        $key = 'weather.' . $zip;
        if (!$this->cache->has($key)) {
            $data = json_decode(
                file_get_contents(
                    $url = "http://api.openweathermap.org/data/2.5/weather?zip={$zip},{$countryCode}&appid=0dde8683a8619233195ca7917465b29d"
                //"http://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid=0dde8683a8619233195ca7917465b29d"
                ),
                true
            );
            printf("Fetching and caching : %s\n", $url);
            $this->cache->set($key, $data);
        }
        return $this->cache->get($key);
    }

    /**
     * @param array $data
     * @return array
     * @throws \Exception
     */
    private function processAssignment(array $data) : array
    {
        $answers = $data['payload'];
        $zip = $answers['zip'] ?? null;
        if (!$zip) {
            throw new \Exception("No 'zip' in payload for assignment " . $data['assignmentId']);
        }
        foreach ($answers as $questionCode => $default) {
            $weatherData = $this->getWeatherData($zip);
            switch ($questionCode) {
                case 'temp':
                case 'temperature':
                    $answers[$questionCode] = $weatherData['main']['temp'];
                    break;
                case 'wind_speed':
                    $answers[$questionCode] = $weatherData['wind']['speed'];
                    break;
                default:
                    // skip
            }
        }

        return $answers;
    }
}
