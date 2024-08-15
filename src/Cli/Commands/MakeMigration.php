<?php

namespace Lune\Cli\Commands;

use Lune\Database\Migrations\Migrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMigration extends Command
{
    protected function configure()
    {
        $this->setName("make:migration")
             ->setDescription("Create new migration file")
             ->addArgument('name', InputArgument::REQUIRED, 'Migration name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        app(Migrator::class)->make($input->getArgument('name'));
        return Command::SUCCESS;
    }
}
