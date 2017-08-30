<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;
use Symfony\Component\Console\Helper\Table;

class UsersSummaryCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('users:summary')
            ->setDescription('Show basic summary of users')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userResource = new UserResource($this->sourceClient);

        $result = $userResource->getList(1, 1000);
        foreach ($result['items'] as $user) {
            $data[] =
                [
                    'username' => $user['username'],
                    'email' => isset($user['email']) ? $user['email'] : '',
                    'roles' => join(',', $user['roles'])
                ];
        }
        $table = new Table($output);
        $table
            ->setHeaders(['name','email','roles'])
            ->setRows($data);
        $table->render();

    }

}
