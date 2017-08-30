<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Survos\Client\Resource\DataImageResource;
use Survos\Client\Resource\SurveyResource;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;
use Symfony\Component\Console\Helper\Table;

class ImagesSummaryCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('images:summary')
            ->setDescription('Show basic summary of image data')
            ->addOption(
                'project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Project code'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectCode = $input->getOption('project-code');

        $imageResource = new DataImageResource($this->sourceClient);

        $result = $imageResource->getList(1, 20, [], [], [], ['project_code' => $projectCode]);
        foreach ($result['items'] as $idx => $image) {
            $data[] =
                [
                    'code' => isset($image['code']) ? $image['code'] : '',
                    'name' => isset($image['name']) ? $image['name'] : '',
                    'image_url'  => isset($image['image_url']) ? $image['image_url'] : '',
                ];
        }
        $table = new Table($output);
        $table
            ->setHeaders(['code', 'name', 'url'])
            ->setRows($data);
        $table->render();
    }
}
