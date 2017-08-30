<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\SurvosClient;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;


class XferMembersCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('xfer:members')
            ->setDescription('Transfer members from source server')
            ->addOption(
                'source-project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Source project code'
            )
            ->addOption(
                'target-project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Target project code'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $sourceProject = $input->getOption('source-project-code');
        $targetProject = $input->getOption('target-project-code');

        $fromMemberResource = new MemberResource($this->sourceClient);
        $toMemberResource = new MemberResource($this->client);
        $projectResource = new ProjectResource($this->client);
        $userResource = new UserResource($this->client);

        $project = $projectResource->getOneBy(['code' => $targetProject]);
        if (!$project) {
            $output->writeln(
                "<error>Target project {$targetProject} not found</error>"
            );
            die();
        }

        $page = 0;
        $perPage = 10;
//        $members = $fromMemberResource->getList($page, $perPage, [], [], [], ['project_code' => $sourceProject]);
        $maxPages = 1;
        while ($page < $maxPages) {
            $members = $fromMemberResource->getList(++$page, $perPage, [], [], [], ['project_code' => $sourceProject]);
            $maxPages = $members['pages'];
            // if no items, return
            if (!count($members['items']) || !$members['total']) {
                break;
            }

            foreach ($members['items'] as $key => $member) {
                if ($isVerbose) {
                    $no = ($page - 1) * $perPage + $key + 1;
                    $output->writeln("{$no} - Reading member #{$member['id']}");
                }

                /*
                 * try to find if member is already there
                 * if yes - ignore. if not - import.
                 * if current member doesn't have any of the $uniqueFields set
                 * then ignore as we won't be able to find it later
                 */
                $uniqueFields = ['code', 'email_within_project', 'phone_within_project'];
                $findByField = false;
                $findByValue = false;
                foreach ($uniqueFields as $field) {
                    if (isset($member[$field])) {
                        $findByField = $field;
                        $findByValue = $member[$field];
                        break;
                    }
                }

                if ($findByField && $findByValue) {
                    $findMember = $toMemberResource->getOneBy(
                        [$findByField => $findByValue, 'project_id' => $project['id']]
                    );
                    if ($findMember) {
                        $output->writeln(
                            "<error>Member {$findByField}:{$findByValue} already exists for project {$project['code']}</error>"
                        );
                        continue;
                    }
                } else {
                    // ignore no-codes for now
                    $output->writeln(
                        "<error>Member #{$member['id']} doesn't have any of the required fields: "
                        .implode(',', $uniqueFields)."</error>"
                    );
                    continue;
                }

                if (array_search($member['permission_type_code'], ['owner']) !== false) {
                    // save admin only if user found
                    // @todo - create user first
                    $user = $userResource->getOneBy(['username' => $member['code']]);
                    if (!$user) {
                        $output->writeln(
                            "<error>Couldn't find user record for admin member {$member['code']} </error>"
                        );
                        continue;
                    }

                    $member['user_id'] = $user['id'];
                }

                $this->processMemberFields($member, $project);
                try {
                    $res = $toMemberResource->save($member);
                    if ($isVerbose) {
                        $output->writeln("New member  #{$res['id']} saved");
                    }
                } catch (\Exception $e) {
                    $output->writeln(
                        "<error>Problem saving {$findByField}:{$findByValue}:{$e->getMessage()} </error>"
                    );
                }
            }


        }

    }

    /**
     * prepare member array to be imported
     *
     * @param $memberFields
     * @param $newProject
     */
    private function processMemberFields(&$memberFields, $newProject)
    {
        unset($memberFields['id']);
        unset($memberFields['created_at']);
        unset($memberFields['updated_at']);
        unset($memberFields['member_type_code']);
        unset($memberFields['task_count']);
        unset($memberFields['assignment_count']);
        unset($memberFields['device_point_count']);
        unset($memberFields['fields_from_project']);
        $memberFields['project_id'] = $newProject['id'];
    }
}
