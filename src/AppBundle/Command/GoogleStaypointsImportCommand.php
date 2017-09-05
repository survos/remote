<?php
namespace AppBundle\Command;

use AppBundle\Command\Base\SqsCommand;
use Survos\Client\Resource\ObserveResource;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoogleStaypointsImportCommand extends SqsCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('remote:import-staypoints')
            ->setDescription('Google Timeline "My Places" Import')
        ;
    }

    protected function processMessage(array $data, array $message) : bool
    {
        $remoteHandler = $this->getContainer()->get('app.remote_handler');
        $remoteHandler->setOutput($this->output);
        $data = $remoteHandler->validateMessage($data);
        $payload = (array)$data['payload'];
        if ($this->input->getOption('verbose')) {
            dump($data, $payload);
        }
        $this->survosClient = $this->getClient($data['apiUrl'], $data['accessToken']);
        $queueType = $this->input->getArgument('queue-type');
        try {
            $localPath = $queueType === 'sqs' ? $remoteHandler->downloadFile($data['parameters']['imageUrl']) : $remoteHandler->resolveLocalPath($data['parameters']['imageUrl']);
            $answersResolver = new OptionsResolver();
            $answersResolver->setDefaults($payload);
            $answers = $answersResolver->resolve($this->processFile($localPath));
        } catch (\Throwable $e) {
            $errorMessage = 'Uploaded file is invalid';
            $remoteHandler->sendError($this->survosClient, $data['channelCode'], $errorMessage, $data['taskId'], $data['assignmentId']);
            $this->output->writeln('unable to process file: '. $e->getMessage());
            return false;
        }
        if ($this->input->getOption('verbose')) {
            dump($answers);
        }

        if ($answers) {
            $data['command'] = 'partial';
            $remoteHandler->sendData($this->survosClient, array_filter($data, function ($key) {
                return in_array($key, ['command', 'taskId','assignmentId','channelCode']);
            }, ARRAY_FILTER_USE_KEY), $answers);
        }

        return true; // use --delete-bad to leave the message in the queue.
    }

    // flatten key/value pairs
    private function flattenArray($a, $prefix=''): array
    {
        $result = [];
        foreach ($a as $key=>$value) {
            if (is_object($value)) {
                $value = (array)$value;
            }
            if (is_array($value))
            {
                $result = array_merge($result, $this->flattenArray($value, $key . '_'));
            } else {
                $result[str_replace(' ', '', $prefix . $key)] = $value;
            }
        }
        return $result;
    }

    protected function processFile($filename)
    {
        if (!file_exists($filename)) {
            throw new \Exception("File '{$filename}' not found");
        }
        $zippedFn = "zip://{$filename}#Takeout/Maps (your places)/Saved Places.json";
        if (false === $json=file_get_contents($zippedFn)) {
            throw new \Exception("The archive doesn't have places, checkout its content: '{$zippedFn}'");
        }
        $items = json_decode($json, true);
        $count = 0;
        $staypoints = [];

        foreach ($items['features'] as $item) {
            $it = $this->flattenArray($item['properties']);
            if (null !== $data = $this->normalizeItem($it)) { // where does member id come in?  $userId)) {
                $staypoints[] = $data;
                $count++;
            }
        }

        // these are the $answers, and need to correspond to the survey questions.
        return [
            'my_places_count' => $count,
            'google_staypoint' => $staypoints,
        ];
    }

    /**
     * @param array $item
     * @return array
     */
    private function normalizeItem(array $item) : array
    {
        return [
            'latitude' => $item['Location_Latitude'] ?? $item['GeoCoordinates_Latitude'],
            'longitude' => $item['Location_Longitude'] ?? $item['GeoCoordinates_Longitude'],
            'name' => $item['Title'],
            'google_maps_url' => $item['GoogleMapsURL']
        ];
    }

    protected function initClient()
    {
        //void
    }

}
