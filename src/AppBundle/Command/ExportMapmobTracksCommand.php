<?php
namespace AppBundle\Command;

use Survos\Client\Resource\TrackResource;
use Survos\Client\Resource\UserResource;
use Survos\Client\SurvosClient;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportMapmobTracksCommand extends ContainerAwareCommand
{
    /** @var OutputInterface */
    private $output;

    public function configure()
    {
        $this->setName('mapmob:export-tracks')
            ->addOption('project', null, InputOption::VALUE_REQUIRED, 'Project to export tracks from')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Mapmob ADMIN Username')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Mapmob ADMIN Password')
            ->addOption('items-per-page', null, InputOption::VALUE_REQUIRED, 'Batch size', 500);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $project = $input->getOption('project');
        $itemsPerPage = $input->getOption('items-per-page');;
        $client = $this->authorize($input->getOption('user'), $input->getOption('password'));
        $file = sys_get_temp_dir().'/'.uniqid($project).'.csv';
        $output->writeln("File is {$file}");
        $writer = new \EasyCSV\Writer($file);
        foreach ($this->getTracksBatches($project, $client, $itemsPerPage) as $index => $batch) {
            if (0 === $index) {
                $header = array_keys($batch[0]);
                $writer->writeRow($header);
            }
            $writer->writeFromArray($batch);
        }
        $output->writeln("Export complete");
    }

    private function getTracksBatches(string $project, SurvosClient $client, int $itemsPerPage): \Generator
    {
        $userRes = new UserResource($client);
        $trackRes = new TrackResource($client);
        $users = $userRes->getList(['oauthClient' => $project]);
        $usersIds = array_column($users['hydra:member'], 'id');
        $this->output->writeln(sprintf('Found %d users: %s', count($usersIds), json_encode($usersIds)));
        foreach ($users['hydra:member'] as $user) {
            $userId = $user['id'];
            $page=0;
            do {
                $page++;
                $tracks = $trackRes->getList(['userId' => $userId], ['timestamp' => 'asc'], $page, $itemsPerPage);
                $totalItems = $tracks['hydra:totalItems'];
                if ($page === 1) {
                    $this->output->writeln("UserId: {$userId} TotalItems: {$totalItems}");
                }
                yield $tracks['hydra:member'];
                $this->output->write('.');
            } while ($page*$itemsPerPage < $totalItems);
            $this->output->writeln("Done");
        }
    }

    private function authorize($login, $pass)
    {
        $client = new SurvosClient('https://api.mapmob.com/api/');
        if (!$client->authorize($login, $pass)) {
            throw new \Exception('Wrong credentials!');
        }
        return $client;
    }
}
