<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Survos\Client\Resource\DataImageResource;
use Survos\Client\Resource\DataResource;
use Survos\Client\Resource\DataTypeResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\TypeResource;
use Survos\Client\Resource\WaveResource;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ImportDataCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('import:data')
            ->setDescription('Import data to new api')
            ->addOption(
                'source-csv-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Source CSV file'
            )
            ->addOption(
                'target-project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Target project code'
            )
            ->addOption(
                'import-wave-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Import wave ID'
            )
            ->addOption(
                'api-endpoint',
                null,
                InputOption::VALUE_REQUIRED,
                'Api endpoint'
            )
            ->addOption(
                'access-token',
                null,
                InputOption::VALUE_OPTIONAL,
                'access-token'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
//        $output->writeln("<error>Command not implemented yet</error>");
//        die();
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $targetProject = $input->getOption('target-project-code');
        $waveId = $input->getOption('import-wave-id');
        $projectResource = new ProjectResource($this->client);
        $project = $projectResource->getByCode($targetProject);

        $sourceCsv = $input->getOption('source-csv-file');
        $csvData = str_getcsv(file_get_contents($sourceCsv), "\n"); //parse the rows
        $columns = str_getcsv(array_shift($csvData), ',');
        $i = 0;
        foreach ($csvData as &$row) {
            $dataResource = new DataResource($this->client);
            $dataArray = str_getcsv($row, ",");
            $dataArray = array_combine($columns, $dataArray);

            $dataTypeResource = new DataTypeResource($this->client);
            $dataType = $dataTypeResource->getByCode($dataArray['DataTypeCode']);

            $data = [
                'code'         => $dataArray['Code'],
                'name'         => $dataArray['Name'],
                'project'      => $project['@id'],
                'dataTypeCode' => $dataType['@id'],
                'isActive'     => $dataArray['IsActive'],
                'createdAt'    => $dataArray['CreatedAt'],
            ];

            if ($dataArray['DataTypeCode'] == 'place') {
                $data = array_merge(
                    $data,
                    [
                        'latitude'  => $dataArray['Latitude'],
                        'longitude' => $dataArray['Longitude'],
                        'zip'       => $dataArray['Zip'],
                        'address'   => $dataArray['Address'],
                    ]
                );
            } elseif ($dataArray['DataTypeCode'] == 'image') {
                $data = array_merge(
                    $data,
                    [
                        'questionCode' => $dataArray['QuestionCode'],
                        'height'       => intval($dataArray['Height']),
                        'width'        => intval($dataArray['Width']),
                        'orientation'  => intval($dataArray['Orientation']),
                        'tag'          => $dataArray['Tag'],
                    ]
                );
            }

            try {
                $dataResource->addForImportWave($waveId, $data);
                $i++;
            } catch (\Exception $e) {
                $output->writeln('<err>'.$e->getMessage().'</err>');
            }
            if ($i > 10) {
                break;
            }
        };


    }

    /**
     * prepare member array to be imported
     *
     * @param $dataImage
     * @param $newProject
     */
    private function processDataFields(&$dataImage, $newProject)
    {
        unset($dataImage['id']);
        unset($dataImage['created_at']);
        unset($dataImage['updated_at']);
        $dataImage['project_id'] = $newProject['id'];
    }
}
