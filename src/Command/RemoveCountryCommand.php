<?php

namespace Pallari\GeonameBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pallari:geoname:remove-country',
    description: 'Removes all data for a specific country from the database',
)]
class RemoveCountryCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $geonameTable,
        private readonly string $countryEntityClass,
        private readonly array $adminTables,
        private readonly string $alternateNameTable,
        private readonly string $hierarchyTable
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('country', InputArgument::REQUIRED, 'The 2-letter ISO country code (e.g. IT)')
            ->addOption('force-delete', 'f', InputOption::VALUE_NONE, 'Physically delete records from the database (default is soft-delete)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $countryCode = strtoupper($input->getArgument('country'));
        $forceDelete = $input->getOption('force-delete');

        $io->title(sprintf('🌍 Removing country: %s', $countryCode));

        $country = $this->em->getRepository($this->countryEntityClass)->findOneBy(['code' => $countryCode]);
        if (!$country) {
            $io->error(sprintf('Country %s not found in database.', $countryCode));
            return Command::FAILURE;
        }

        $mode = $forceDelete ? 'PHYSICAL DELETE (FREE DISK SPACE)' : 'SOFT DELETE (MARK AS DELETED)';
        if (!$io->confirm(sprintf('Are you sure you want to remove %s using %s mode?', $countryCode, $mode), false)) {
            $io->note('Operation cancelled.');
            return Command::SUCCESS;
        }

        $conn = $this->em->getConnection();
        $platform = $conn->getDatabasePlatform();

        try {
            // 1. Disable Country
            $io->text('Disabling country in registry...');
            $country->setIsEnabled(false);
            $this->em->flush();

            if ($forceDelete) {
                // 2. Physical Delete
                $io->section('Performing Physical Deletion');

                // Hierarchy (Requires JOIN since it only has IDs)
                $io->text('Cleaning hierarchy...');
                $sqlHierarchy = sprintf(
                    "DELETE h FROM %s h INNER JOIN %s g ON h.childid = g.geonameid WHERE g.country_code = ?",
                    $platform->quoteIdentifier($this->hierarchyTable),
                    $platform->quoteIdentifier($this->geonameTable)
                );
                // Note: For Postgres/SQLite compatibility, we'd need a subquery, but since we optimized for MySQL/Postgres
                // let's use a more universal subquery approach.
                $sqlHierarchy = sprintf(
                    "DELETE FROM %s WHERE childid IN (SELECT geonameid FROM %s WHERE country_code = ?)",
                    $platform->quoteIdentifier($this->hierarchyTable),
                    $platform->quoteIdentifier($this->geonameTable)
                );
                $count = $conn->executeStatement($sqlHierarchy, [$countryCode]);
                $io->writeln(sprintf(' <info>✔</info> Removed %d hierarchy links', $count));

                // Alternate Names
                $io->text('Cleaning alternate names...');
                $sqlAlt = sprintf(
                    "DELETE FROM %s WHERE geonameid IN (SELECT geonameid FROM %s WHERE country_code = ?)",
                    $platform->quoteIdentifier($this->alternateNameTable),
                    $platform->quoteIdentifier($this->geonameTable)
                );
                $count = $conn->executeStatement($sqlAlt, [$countryCode]);
                $io->writeln(sprintf(' <info>✔</info> Removed %d translations', $count));

                // Admin Tables
                foreach ($this->adminTables as $level => $tableName) {
                    if (empty($tableName)) continue;
                    $io->text("Cleaning admin table $level ($tableName)...");
                    $sqlAdmin = sprintf("DELETE FROM %s WHERE country_code = ?", $platform->quoteIdentifier($tableName));
                    $count = $conn->executeStatement($sqlAdmin, [$countryCode]);
                    $io->writeln(sprintf(' <info>✔</info> Removed %d administrative entries', $count));
                }

                // Main Geoname Table
                $io->text('Cleaning main geoname table...');
                $sqlMain = sprintf("DELETE FROM %s WHERE country_code = ?", $platform->quoteIdentifier($this->geonameTable));
                $count = $conn->executeStatement($sqlMain, [$countryCode]);
                $io->writeln(sprintf(' <info>✔</info> Removed %d toponyms', $count));

            } else {
                // 2. Soft Delete
                $io->section('Performing Soft Deletion');
                $sqlSoft = sprintf("UPDATE %s SET is_deleted = 1 WHERE country_code = ?", $platform->quoteIdentifier($this->geonameTable));
                $count = $conn->executeStatement($sqlSoft, [$countryCode]);
                $io->success(sprintf('Marked %d records as deleted.', $count));
            }

            $io->success(sprintf('Country %s successfully removed.', $countryCode));
        } catch (\Exception $e) {
            $io->error('Operation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
