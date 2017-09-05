<?php
namespace AppBundle\Services;

use Survos\Client\SurvosClient;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Survos\Client\Resource\ChannelResource;

class RemoteHandlerTools
{
    private $platformPath;

    /** @var OutputInterface */
    private $output;

    public function __construct(string $platformPath)
    {
        $this->platformPath = $platformPath;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function resolveLocalPath($url): string
    {
        $platformRoot = realpath($this->platformPath . '/web');
        if (!file_exists($platformRoot)) {
            throw new \Exception("Can't find the platform root. It's supposed to be here: {$platformRoot}");
        }
        if (!preg_match('/(\/uploads.+$)/', $url, $matches)) {
            throw new \Exception("Can't parse file location from url: {$url}");
        }
        $path = $platformRoot . $matches[1];
        if (!file_exists($path)) {
            throw new \Exception("Can't resolve local path ({$path}) from url: {$url}");
        }
        return $path;
    }

    public function downloadFile($url)
    {
        $path = sys_get_temp_dir() . '/' . md5($url). '.zip';
        if (!file_exists($path)) {
            $newfname = $path;
            if ($file = fopen($url, 'rb'))
            {
                $newf = fopen($newfname, 'wb');
                if ($newf) {
                    while (!feof($file)) {
                        fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
                    }
                }
                fclose($file);
                if ($newf) {
                    fclose($newf);
                }
            }
        }
        return $path;
    }

    /**
     * send the answers back to using ChannelResource::sendData
     */
    public function sendData(SurvosClient $client, array $channelData, array $answers): array
    {
        $channelData = (new OptionsResolver())->setDefaults([
            'assignmentId' => null,
            'command' => 'complete',
        ])->setRequired([
            'taskId',
            'channelCode'
        ])->resolve($channelData);

        dump(__METHOD__, $answers);
        $currentUserId = $client->getLoggedUser()['id'];
        $res = new ChannelResource($client);
        $response = $res->sendData($channelData['channelCode'], $sentData = [
            'answers' => $answers,
            // seems like this meta-data could be grouped
            'command' => $channelData['command'],
            'memberId' => $currentUserId,
            'taskId' => $channelData['taskId'],
            'assignmentId' => $channelData['assignmentId'],
        ]);
        $this->writeln(sprintf("Submitted to %s:\n %s\nReceived: %s",
            $res->getLastRequestPath(),
            json_encode($sentData, JSON_PRETTY_PRINT),
            json_encode($response, JSON_PRETTY_PRINT)) );
        return $response;
    }

    /**
     * send error to ChannelResource::sendData
     */
    public function sendError(SurvosClient $client, string $channelCode, string $error, int $taskId, ?int $assignmentId): array
    {
        dump(__METHOD__, $error);
        $currentUserId = $client->getLoggedUser()['id'];
        $res = new ChannelResource($client);
        $response = $res->sendData($channelCode, [
            'error' => $error,
            'memberId' => $currentUserId,
            'taskId' => $taskId,
            'assignmentId' => $assignmentId,
        ]);
        $this->writeln('Submitted: ' . json_encode($response, JSON_PRETTY_PRINT));
        return $response;
    }

    /**
     * @param array $data
     * @return array
     */
    public function validateMessage($data)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined([
            'action', 'deployment', 'parameters',
            //TODO: we don't use these params
            'statusEndpoint', 'receiveEndpoint', 'receiveMethod',
        ]);
        $resolver->setRequired(['payload', 'mapmobToken', 'apiUrl', 'accessToken', 'taskId', 'assignmentId', 'channelCode']);
        return $resolver->resolve((array) $data);
    }

    private function writeln($messages)
    {
        if ($this->output) {
            $this->output->writeln($messages);
        }
    }

}
