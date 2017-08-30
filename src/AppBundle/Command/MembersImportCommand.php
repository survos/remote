<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\SurvosClient;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;


class MembersImportCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('members:import')
            ->setDescription('Import members from CSV file')
            ->addArgument(
                'filename',
                InputArgument::REQUIRED,
                'path to the CSV file'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('filename');
        $projectResource = new ProjectResource($this->client);
        $userResource = new UserResource($this->client);
        $memberResource = new MemberResource($this->client);

        $reader = new \EasyCSV\Reader('new_members.csv');

        while ($row = $reader->getRow()) {
            $project = $projectResource->getByCode($row['project_code']);
            // we ned that user for admins, maybe it should be separate field
            $user = $userResource->getOneBy(['username' => $row['username']]);

            if (!$user) {
                print "user '{$row['username']}' not found\n";
            }
            try {
                $res = $memberResource->save(
                    [
                        'code'                 => $row['code'],
                        'project_id'           => $project['id'],
                        'user_id'              => $user['id'],
                        'permission_type_code' => $row['permission_type_code'],
                    ]
                );
            } catch (\Exception $e) {
                print "Error importing member {$row['code']}:".$e->getMessage()."\n";
            }

        }

        $output->writeln($text);
    }
}
