<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;

class ProjectsImportCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('projects:import')
            ->setDescription('Import projects from CSV file')
            ->addArgument(
                'filename',
                InputArgument::REQUIRED,
                'path to the CSV file'
            )
            ->addOption(
                'server-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Server code'
            )
            ->addOption(
                'project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Project code'
            )
            ->addOption(
                'timezone-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'Timezone ID from Survos (default 295 / America/New_York)',
                295
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('filename');

        $serverCode = $input->getOption('server-code');
        $timezoneId = $input->getOption('timezone-id');
        $projectCode = $input->getOption('project-code');



        $userResource = new UserResource($this->client);
        /** @type MemberResource $memberResource */
        $memberResource = new MemberResource($this->client);
        $projectResource = new ProjectResource($this->client);


        /** @type ProjectResource $resource */
        $resource = new ProjectResource($this->client);

        $reader = new \EasyCSV\Reader($filename, 'r', true);

        while ($row = $reader->getRow()) {
            //code,name,description,timezone_id

            $code = $row['code'];
            if ($projectCode && ($code != $projectCode))
            {
                continue;
            }
            $params = [];
            if ($project = $resource->getOneBy(['code' => $code])) {
                $params['id'] = $project['id'];
            }

            try {
                /*
                $res = $resource->save(
                    array_merge(
                        $params,
                        [
                            'title'                  => $name = $row['name'],
                            'code'                   => $code,
                            'timezone_id'            => $timezoneId,
                            'description'            => $name." Project",
                            'background_server_code' => $serverCode,
                        ]
                    )
                );
                */
            } catch (\Exception $e) {
                printf("Error saving project: %s\n", $e->getMessage());
                printf("Project $code already exists\n");
            }

            $project = $projectResource->getByCode($code);

            $res = $resource->addModule($code, 'turk');
            $staffAdmins = ['tac','ho449'];

            try {
                foreach (array_merge([$code], $staffAdmins) as $idx => $username) {
                    $user = $userResource->getOneBy(['username' => $username]);

                    if (!$user) {
                        print "user '$username' not found\n";
                        continue;
                    }

                    $params = [
                        'code'                 => $username,
                        'project_id'           => $project['id'],
                        'user_id'              => $user['id'],
                        'permission_type_code' => 'owner',
                        'enrollment_status_code' => 'notenrolled'
                    ];
                    $member = $memberResource->getOneBy(['code' => $username, 'project_id' => $project['id']]);
                    if ($member) {
                        $output->writeln(
                            "<error>Member '$username' already exists for project ".$project['code']."</error>"
                        );
                        continue;
                    }

                    print "Saving member '$username' for project ".$project['code']."\n";
                    $res = $memberResource->save($params);
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Error importing member: {$e->getMessage()}</error>");
            }


        }
    }
}
