<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Survos\Client\Resource\SurveyResource;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;
use Symfony\Component\Console\Helper\Table;

class ProjectsListCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('projects:list')
            ->setDescription('Show basic summary of a project')
            ->addOption(
                'project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Project code'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectResource = new ProjectResource($this->sourceClient);
        $memberResource = new MemberResource($this->sourceClient);
        $surveyResource = new SurveyResource($this->sourceClient);

        $result = $projectResource->getList();
        foreach ($result['items'] as $idx => $project) {
            $projectCode = $project['code'];
            $result = $memberResource->getList(1, 1000, ['project_id' => $project['id'], 'permission_type_code' => 'owner']);
            $owners = implode(
                ', ',
                array_map(
                    function ($member) { return isset($member['code'])?$member['code']:('#'.$member['id']); },
                    $result['items']
                )
            );
            $result = $surveyResource->getList(1, 1000, ['project_id' => $project['id']]);
            $surveys = implode(
                "\n",
                array_map(
                    function ($survey) { return '"' . $survey['name'] . '"'; },
                    $result['items']
                )
            );
            $data[] =
                [
                    'code' => $projectCode,
                    'owners' => $owners,
                    'surveys' => $surveys,
                ];
        }
        $table = new Table($output);
        $table
            ->setHeaders(['code', 'owners', 'surveys'])
            ->setRows($data);
        $table->render();
    }
}
