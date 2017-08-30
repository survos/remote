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
use Symfony\Component\OptionsResolver\OptionsResolver;

class TrackTasksCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('track:tasks')
            ->setDescription('Process Tracking Tasks')
            ->addOption(
                'project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Source project code'
            )
            ->addOption(
                'survey',
                null,
                InputOption::VALUE_REQUIRED,
                'Survey code'
            )
            ->addOption(
                'member',
                null,
                InputOption::VALUE_REQUIRED,
                'Member code'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $projectCode = $input->getOption('project-code');

        $memberResource = new MemberResource($this->sourceClient);


        $project = 'behattest';
        $date = '2016-01-31';

        $assignments = $this->getTrackingAssignments($client = $this->sourceClient, [
            'project' => $project,
            'memberCode' => $input->getOption('member'),
            'date' => $date,
            'surveyCode' => $input->getOption('survey')
        ]);

        $surveysByTask = $this->getSurveysByTask($client);
        if ($assignments) {
            foreach ($assignments['items'] as $assignment) {
                $taskId = $assignment['task_id'];
                $survey = $surveysByTask[$taskId];
                $trackResponse = $this->getTracks($client, $assignment['scheduled_time'], $assignment['scheduled_end_time']);
                $tracks = $trackResponse['items'];
                if ($isVerbose) {
                    $output->writeln(sprintf('%d points for %s to %s', count($tracks), $assignment['scheduled_time'], $assignment['scheduled_end_time']));
                }
                $newData = [];
                foreach ($survey['questions'] as $question) {
                    if ($isVerbose) {
                        $output->writeln(sprintf("Checking question '{$question['code']}' for %s",  $assignment['member_code']));
                    }
                    switch ($question['code']) {
                        case 'point_count':
                            $newData[$question['code']] = count($tracks);
                            break;
                        case 'center_lat_lng':
                            $center = $this->getTracksCenter($tracks);
                            if ($center) {
                                $newData[$question['code']] = $center;
                            }
                            break;
                        default:
                            // ignore other fields
                    }
                    if ($newData) {
                        $flatData = array_merge(
                            $assignment['flat_data'] ?: [],
                            $newData
                        );
                        $this->saveAssignment($client, [
                            'id' => $assignment['id'],
                            'flat_data' => $flatData,
                        ]);
                    }
                }
            }
        }
    }

    function getSurveysByTask($client)
    {
        $resource = new \Survos\Client\Resource\TaskResource($client);
        $tasks = $resource->getList(null, null, ['task_type_code' => 'device']);
        $surveysByTask = [];
        foreach ($tasks['items'] as $task) {
            $surveysByTask[$task['id']] = isset($task['survey_json']) ? json_decode($task['survey_json'], true) : null;
        }
        return $surveysByTask;
    }

        function getTrackingAssignments($client, Array $options)
        {
            $resolver = new OptionsResolver();
            $resolver->setDefaults([
                'project' => null,
                'memberCode' => null,
                'surveyCode' => null,
                'date' => null,
            ]);
            $options = $resolver->resolve($options);
            $resource = new \Survos\Client\Resource\AssignmentResource($client, $params = []);
            $filter = [];
            // $filter = ['score' => 0];
            $comparison = ['score' => \Survos\Client\SurvosCriteria::GREATER_THAN];
            $params = ['task_type_code' => 'device'];
            if ($project = $options['project']) {
                $params['project_code'] = $project;
            }
            if ($memberCode = $options['memberCode']) {
                $params['member_code'] = $memberCode;
            }
            if ($surveyCode = $options['surveyCode']) {
                $params['survey_code'] = $surveyCode;
            }
            if ($date = $options['date']) {
                $filter['scheduled_time'] = $date;
                $filter['scheduled_end_time'] = $date;
                $comparison['scheduled_time'] = \Survos\Client\SurvosCriteria::LESS_EQUAL;
                $comparison['scheduled_end_time'] = \Survos\Client\SurvosCriteria::GREATER_EQUAL;
            }
            return $resource->getList(null, null, $filter=[], $comparison, null, $params);
        }

        function saveAssignment($client, $data)
        {
            $resource = new \Survos\Client\Resource\AssignmentResource($client);
            $response = $resource->save($data);
        }

        function getTracks($client, $fromTime, $toTime)
        {
            $filter = ['timestamp' => [$fromTime, $toTime]];
            $comparison = ['timestamp' => \Survos\Client\SurvosCriteria::BETWEEN];
            $orderBy = [['column' => 'timestamp', 'dir' => \Survos\Client\SurvosCriteria::ASC]];
            $resource = new \Survos\Client\Resource\TrackResource($client);
            return $resource->getList(null, null, $filter, $comparison, $orderBy);
        }

        function getTracksCenter(array $tracks)
        {
            $points = [];
            foreach ($tracks as $track) {
                $points[] = [$track['latitude'], $track['longitude']];
            }
            return $this->GetCenterFromDegrees($points);
        }

        /**
         * Get a center latitude,longitude from an array of like geopoints
         * Taken from here http://stackoverflow.com/a/18623672
         * Eventually can be used https://github.com/bdelespierre/php-kmeans
         * @param array $data
         * @return array|bool
         */
        function GetCenterFromDegrees($data)
        {
            if (!is_array($data) || empty($data)) {
                return false;
            }

            $num_coords = count($data);

            $X = 0.0;
            $Y = 0.0;
            $Z = 0.0;

            foreach ($data as $coord) {
                $lat = $coord[0] * pi() / 180;
                $lon = $coord[1] * pi() / 180;

                $a = cos($lat) * cos($lon);
                $b = cos($lat) * sin($lon);
                $c = sin($lat);

                $X += $a;
                $Y += $b;
                $Z += $c;
            }

            $X /= $num_coords;
            $Y /= $num_coords;
            $Z /= $num_coords;

            $lon = atan2($Y, $X);
            $hyp = sqrt($X * $X + $Y * $Y);
            $lat = atan2($Z, $hyp);

            return array($lat * 180 / pi(), $lon * 180 / pi());
        }
    }
