<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Survos\Client\Resource\TaskResource;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\SurvosClient;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;


class TasksActionCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('tasks:action')
            ->setDescription('Do an action on selected tasks')
            ->addOption(
                'project-code',
                null,
                InputOption::VALUE_OPTIONAL,
                'Source project code'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        die('not implemented yet');
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $projectCode = $input->getOption('project-code');
//        $limit = $input->getOption('limit');

        $taskResource = new TaskResource($this->sourceClient);

        $page = 0;
        $perPage = 10;
        $maxPages = 1;
        $criteria = [];

        $data = [];
        while ($page < $maxPages) {
            $tasks = $taskResource->getList(
                ++$page,
                $perPage,
                $this->getTaskCriteria(),
                [],
                [],
                ['project_code' => $projectCode, 'PII' => 1]
            );
            $maxPages = $tasks['pages'];
            // if no items, return
            if (!count($tasks['items']) || !$tasks['total']) {
                break;
            }

            foreach ($tasks['items'] as $key => $task) {
                if ($isVerbose) {
                    $no = ($page - 1) * $perPage + $key + 1;
                    $output->writeln("{$no} - Reading member #{$task['id']}");
                }

                if ($this->checkAction($task)) {
                    // do action (needs implementing in task resource helper)
//                    $taskResource->setApplicantsStatus([$task['id']], 'accept', 'Accepted via API');

                    if ($isVerbose) {
                        $output->writeln("action on task  #{$task['id']}");
                    }
                }
            }
        }


    }

    /**
     * @param $task
     * @return bool
     */
    private function checkAction($task)
    {

        return true;
    }

    /**
     * @param $task
     * @return array
     */
    private function getTaskCriteria()
    {
        return [

        ];
    }

}
