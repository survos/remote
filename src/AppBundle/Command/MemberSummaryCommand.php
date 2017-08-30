<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\SurvosClient;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;


class MemberSummaryCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('members:summary')
            ->setDescription('Show members summary')
            ->addOption(
                'project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Source project code'
            )
            ->addOption(
                'enrollment-status-code',
                null,
                InputOption::VALUE_OPTIONAL,
                'Enrollment status code'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $projectCode = $input->getOption('project-code');
        $enrollmentStatusCode = $input->getOption('enrollment-status-code');

        $memberResource = new MemberResource($this->sourceClient);

        $page = 0;
        $perPage = 10;
        $maxPages = 1;
        $criteria = [];
        if ($enrollmentStatusCode) {
            $criteria['enrollment_status_code'] = $enrollmentStatusCode;
        }
        $data = [];
        while ($page < $maxPages) {
            $members = $memberResource->getList(++$page, $perPage, $criteria, [], [], ['project_code' => $projectCode]);
            $maxPages = $members['pages'];
            // if no items, return
            if (!count($members['items']) || !$members['total']) {
                break;
            }

            foreach ($members['items'] as $key => $member) {
                if ($isVerbose) {
                    $no = ($page - 1) * $perPage + $key + 1;
//                    $output->writeln("{$no} - Reading member #{$member['id']}");
                }

                $data[] = [
                    'code'                   => isset($member['code']) ? $member['code'] : ('#'.$member['id']),
                    'email'                  => isset($member['email_within_project'])
                        ? $member['email_within_project'] : '-',
                    'phone'                  => isset($member['phone_within_project'])
                        ? $member['phone_within_project'] : '-',
                    'enrollment_status_code' => isset($member['enrollment_status_code'])
                        ? $member['enrollment_status_code'] : '-',
                ];
            }
        }

        $table = new Table($output);
        $table
            ->setHeaders(['code', 'email', 'phone', 'enrollment_status_code'])
            ->setRows($data);
        $table->render();

    }

}
