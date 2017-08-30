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


class MembersAcceptCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('members:accept')
            ->setDescription('Accept members by given criteria')
            ->addOption(
                'project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Source project code'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $projectCode = $input->getOption('project-code');

        $memberResource = new MemberResource($this->sourceClient);

        $page = 0;
        $perPage = 10;
        $maxPages = 1;
        $criteria = [];

        $data = [];
        while ($page < $maxPages) {
            $members = $memberResource->getList(
                ++$page,
                $perPage,
                $this->getMemberCriteria(),
                [],
                [],
                ['project_code' => $projectCode, 'PII' => 1]
            );
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

                if ($this->checkAccept($member)) {
                    // activate member
                     $memberResource->setApplicantsStatus([$member['id']],'accept','Accepted via API');

                    if ($isVerbose) {
                        $output->writeln("Accepting member #{$member['id']}");
                    }
                }
            }
        }


    }

    /**
     * @param $member
     * @return bool
     */
    private function checkAccept($member)
    {

        return $member['email_within_project'] == 'piogrek+testsmsreg1@gmail.com';
//        return $member['id'] % 2 !== 0;
    }

    /**
     * @param $member
     * @return array
     */
    private function getMemberCriteria()
    {
        return [
            'enrollment_status_code' => 'applicant',
        ];
    }

}
