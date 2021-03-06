<?php
namespace AppBundle\Command;

use Survos\Client\Resource\OAuthAccessToken;
use Survos\Client\Resource\UserResource;
use Survos\Client\SurvosClient;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportMapmobUsersCommand extends ContainerAwareCommand
{
    /** @var OutputInterface */
    private $output;

    public function configure()
    {
        $this->setName('mapmob:export-users')
            ->addOption('project', null, InputOption::VALUE_REQUIRED, 'Project to export users from')
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
        $users = $this->getUsers($project, $client, $itemsPerPage);
        foreach ($users as $key => $user) {
            $this->output->write(sprintf('Obtaining list of granted projects for user %s...', $user['username']));
            $grantedProjects = $this->getGrantedProjects($user['username'], $client, $itemsPerPage);
            $this->output->writeln('Done');
            $user = $this->normalize($user, $grantedProjects);
            if ($key === 0) {
                $header = array_keys($user);
                $writer->writeRow($header);
            }
            $writer->writeRow($user);
        }
        $output->writeln("Export complete");
    }

    private function getUsers(?string $project, SurvosClient $client, int $itemsPerPage): array
    {
        $userRes = new UserResource($client);
        $filter = [];
        if ($project) {
            $filter['oauthClient'] = $project;
        }
        $users = $userRes->getList($filter, [], null, $itemsPerPage);
        $usersIds = array_column($users['hydra:member'], 'id');
        $this->output->writeln(sprintf('Found %d users: %s', count($usersIds), json_encode($usersIds)));
        return $users['hydra:member'];
    }

    private function getGrantedProjects(string $username, SurvosClient $client, int $itemsPerPage): array
    {
        $res = new OAuthAccessToken($client);
        $tokens = $res->getList(['username' => $username], [], null, $itemsPerPage);
        return array_unique(array_column($tokens['hydra:member'], 'clientCode'));
    }

    private function normalize(array $user, array $grantedProjects)
    {
        $user['roles'] = isset($user['roles']) ? implode('|', $user['roles']) : '';
        unset($user['complianceSummary']);
        $user['grantedProjects'] = implode('|', $grantedProjects);
        return $user;
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
