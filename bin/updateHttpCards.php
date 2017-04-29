<?php

<<<CONFIG
packages:
    - "symfony/console: ^3.2"
    - "symfony/filesystem: ^3.2"
    - "symfony/finder: ^3.2"
    - "gitonomy/gitlib: ^1.0"
CONFIG;

use Gitonomy\Git\Admin;
use Gitonomy\Git\Repository;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;

$output = new ConsoleOutput();
$fs = new Filesystem();

$httpCardDirectory = realpath(__DIR__.'/..').'/http/';
$tmpDirectory = sys_get_temp_dir().'/'.uniqid('AnkiSymfony3Cards_').'/';
$httpRepository = 'git@github.com:for-GET/know-your-http-well.git';

function writeCard($fs, $filepath, $question, $answer, array $tags = []) {
    if ($fs->exists($filepath)) {
        $fs->remove($filepath);
    }
    $fs->dumpFile($filepath, sprintf(
        "tags: %s\r\n-----\r\n%s\r\n-----\r\n%s",
        implode(' ', $tags),
        $question,
        $answer
    ));
}

try {

    $output->writeln('<info>Running checks...</info>');

    if (!$fs->exists($httpCardDirectory)) {
        $fs->mkdir($httpCardDirectory);
    }

    $fs->mkdir($tmpDirectory);

    if (!Admin::isValidRepository($httpRepository)) {
        $output->writeln(sprintf(
            '<error>The repository url doesn\'t seem valid or reachable, given: "%s".</error>',
            $httpRepository
        ));
        return;
    }

    // Gather the repository
    $output->writeln('<info>Downloading the HTTP repository...</info>');
    Admin::cloneTo($tmpDirectory, $httpRepository, false);
    new Repository($tmpDirectory);

    // Extract statuses cards
    $output->writeln('<info>Extracting statuses data...</info>');

    $filename = 'status-codes.md';
    $filepath = $tmpDirectory.$filename;

    if (!$fs->exists($filepath)) {
        $output->writeln(sprintf(
            '<error>The file "%s" doesn\'t exists.</error>',
            $filename
        ));
        return;
    }

    preg_match_all(
        '#`(([0-9x])[0-9x]{2})` \| (?:\*\*)?([^*|]*)(?:\*\*)? \| "([^"]*)"(?: ~ \[([^\]]*)\]\([^)]*\))? \| (.*)\n#Uu',
        file_get_contents($filepath),
        $matches,
        PREG_SET_ORDER
    );

    if (0 === count($matches)) {
        $output->writeln('<error>Cannot find the statuses in the file.</error>');
        return;
    }

    $output->writeln('<info>Writing statuses cards...</info>');
    $progress = new ProgressBar($output, count($matches));
    $progress->start();

    $statusesCardDirectory = $httpCardDirectory.'/statuses/';
    if (!$fs->exists($statusesCardDirectory)) {
        $fs->mkdir($statusesCardDirectory);
    }

    $familyCardDirectory = $statusesCardDirectory.'/family/';

    if (!$fs->exists($familyCardDirectory)) {
        $fs->mkdir($familyCardDirectory);
    }

    $currentFamily = '';
    foreach ($matches as $cardData) {

        $question = $cardData[1];
        $cardData[6] = preg_replace('# *<br> *#', "\r\n", $cardData[6]);

        if (!$fs->exists($statusesCardDirectory.$currentFamily)) {
            $fs->mkdir($statusesCardDirectory.$currentFamily);
        }

        if (!empty($cardData[5])) {

            // Family status
            $answer = $cardData[3].' ~ '.$cardData[5]."\r\n".$cardData[4];

            if (!empty($cardData[6])) {
                $answer .= "\r\n\r\n".$cardData[6];
            }

            $filepath = $familyCardDirectory.$question.'.card';

        } else {

            $statusCardDirectory = $statusesCardDirectory.$cardData[2].'xx/';

            if (!$fs->exists($statusCardDirectory)) {
                $fs->mkdir($statusCardDirectory);
            }

            // Usual status
            $answer = $cardData[3]."\r\n".$cardData[4];

            if (!empty($cardData[6])) {
                $answer .= "\r\n\r\n".$cardData[6];
            }

            $filepath = $statusCardDirectory.'/'.$question.'.card';

        }

        writeCard($fs, $filepath, $question, $answer);
        $progress->advance();
    }

    $progress->finish();
    $output->writeln('');

    // Remove the repository
    $output->writeln('<info>Doing some cleaning...</info>');
    $fs->remove($tmpDirectory);

    $output->writeln('<info>End</info>');

} catch (\Exception $e) {

    // Try to remove the temporary dir
    if ($fs->exists($tmpDirectory)) {
        $fs->remove($tmpDirectory);
    }

    // Then back to normal :')
    throw $e;
}
