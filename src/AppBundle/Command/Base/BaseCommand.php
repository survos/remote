<?php

namespace AppBundle\Command\Base;

use Survos\Client\SurvosClient;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends ContainerAwareCommand
{
    /** @var string (should be overridden in subclass) */
    protected $name = 'demo:base';

    /** @var string (should be overridden in subclass) */
    protected $description = 'Override $this->description property in subclass';

    /** @var string (should be overridden in subclass) */
    protected $help = '';

    /** @var array */
    protected $parameters;

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @type SurvosClient */
    protected $client;

    /** @type SurvosClient */
    protected $sourceClient;

    protected function configure()
    {
        parent::configure();
        $this->setName($this->name)
            ->setDescription($this->description);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->parameters = $this->getContainer()->getParameter('survos');
        if (!is_array($this->parameters) || !count($this->parameters)) {
            $output->writeln('<error>Config file could not be found or is not correct</error>');
        }
        $this->initClient();
    }

    protected function initClient()
    {
        // configure target client
        $this->client = new SurvosClient($this->parameters['target']['endpoint']);

        if ($this->input->hasOption('access-token') && $this->input->getOption('access-token')) {
            $authResult = $this->client->authorize($this->input->getOption('access-token'));
        } else {
            $authResult = $this->client->authorize(
                $this->parameters['target']['username'],
                $this->parameters['target']['password']
            // @todo Commenting out for now, since current authorize() does not use them
//                $this->parameters['target']['client_id'],
//                $this->parameters['target']['client_secret']
            );
        }

        if (!$authResult) {
            $this->output->writeln(
                "<error>Wrong credentials for target endpoint: {$this->parameters['target']['endpoint']}</error>"
            );
        }

        // configure source client (optional)
        $this->sourceClient = null;

        if ($this->parameters['source']) {
            $this->sourceClient = new SurvosClient($this->parameters['source']['endpoint']);

            if (!$this->sourceClient->authorize(
                $this->parameters['source']['username'],
                $this->parameters['source']['password']
            // $this->parameters['source']['client_id'],
            // $this->parameters['source']['client_secret']
            )
            ) {
                $this->output->writeln(
                    "<error>Wrong credentials for source endpoint: {$this->parameters['source']['endpoint']}</error>"
                );
            }
        }
    }

    /**
     * @param array           $data
     * @param OutputInterface $output
     */
    protected function printTableResponse(array $data, OutputInterface $output)
    {
        $table = new Table($output);
        $columns = [];
        foreach ($data as $line) {
            $this->processRow($line);
            $columns = array_unique(array_merge($columns, array_keys($line)));
        }
        // make sure all rows have the same columns
        $output = [];
        foreach ($data as $line) {
            $row = [];
            foreach ($columns as $column) {
                $row[$column] = isset($line[$column]) ? $line[$column] : '';
            }

            $output[] = $row;
        }
        $table
            ->setHeaders($columns)
            ->addRows($output)
            ->render();
    }

    /**
     * @param array $data
     * @param OutputInterface $output
     */
    protected function printJsonResponse(array $data, OutputInterface $output)
    {
        $output->write(json_encode($data));
    }

    /**
     * @param string          $format
     * @param array           $data
     * @param OutputInterface $output
     */
    protected function printResponse($format = 'table', array $data, OutputInterface $output)
    {
        $method = "print".ucfirst($format)."Response";
        $this->$method($data, $output);
    }

    /**
     * @param object $data
     */
    protected function processRow(&$data)
    {

    }
}
