<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Survos\Client\Resource\SurveyResource;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\SurvosClient;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;


class SurveysXferCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('surveys:xfer')
            ->setDescription('Transfer surveys from source server')
            ->addOption(
                'source-project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Source project code'
            )
            ->addOption(
                'target-project-code',
                null,
                InputOption::VALUE_OPTIONAL,
                'Target project code'
            )
            ->addOption(
                'source-survey-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Source survey ID'
            )
            ->addOption(
                'target-survey-code',
                null,
                InputOption::VALUE_OPTIONAL,
                'Target survey code'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;

        $sourceProjectCode = $input->getOption('source-project-code');
        $targetProjectCode = $input->getOption('target-project-code');
        $targetSurveyCode = $input->getOption('target-survey-code');

        $sourceProjectResource = new ProjectResource($this->sourceClient);
        $projectResource = new ProjectResource($this->client);

        $sourceProject = $sourceProjectResource->getByCode($sourceProjectCode);
        $targetProject = $projectResource->getByCode($targetProjectCode);

        if (!$sourceProject) {
            $output->writeln("<error>Project '{$sourceProjectCode}' not found</error>");
            return false;
        }
        if (!$targetProject) {
            $output->writeln("<error>Project '{$targetProjectCode}' not found</error>");
            return false;
        }

        $sourceSurveyId = $input->getOption('source-survey-id');

        /** @type SurveyResource $fromSurveyResource */
        $fromSurveyResource = new SurveyResource($this->sourceClient);
        $toSurveyResource = new SurveyResource($this->client);

        $survey = $fromSurveyResource->getById($sourceSurveyId);
        $surveyJson = $fromSurveyResource->getExportJson($survey['id']);
        if ($targetSurveyCode) {
            $surveyJson['code'] = $targetSurveyCode;
        }

        $result = $toSurveyResource->importSurvey(
            [
                'import_data'   => $surveyJson,
                'name'          => $survey['name'],
                'code'          => $survey['code'],
                'project_id'    => $targetProject['id'],
                'category_code' => $survey['category_code'],
                'description'   => $survey['description'],
            ]
        );
        $output->writeln("Survey {$result['code']} #{$result['id']} transferred");


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
