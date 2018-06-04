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
$tmpDirectory = sys_get_temp_dir().'/'.uniqid('AnkiSymfonyCards_').'/';
$httpRepository = 'git@github.com:for-GET/know-your-http-well.git';

function writeCard($fs, $filepath, $question, $answer, array $tags = [])
{
    $fs->dumpFile($filepath, sprintf(
        "tags: %s\r\n-----\r\n%s\r\n-----\r\n%s\r\n",
        implode(' ', $tags),
        $question,
        $answer
    ));
}

function extractAndAddLinks(&$text, $haystack)
{
    preg_match_all('#\[([^\]]*)\]\(([^)]*)\)#Uu', $haystack, $links, PREG_SET_ORDER);
    if (0 < count($links)) {
        $text .= "\r\n\r\n";
        $htmlLinks = [];
        foreach ($links as $link) {
            $htmlLinks[] = sprintf('<a href="%s">%s</a>', $link[2], $link[1]);
        }
        $text .= implode("\r\n", $htmlLinks);
    }
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

    $familyCardDirectory = $statusesCardDirectory.'/families/';

    if (!$fs->exists($familyCardDirectory)) {
        $fs->mkdir($familyCardDirectory);
    }

    foreach ($matches as $cardData) {
        $question = $cardData[1];

        if (!empty($cardData[5])) {

            // Family status
            $answer = $cardData[3].' ~ '.$cardData[5]."\r\n".$cardData[4];
            $filepath = $familyCardDirectory.$question.'.card';
        } else {
            $statusCardDirectory = $statusesCardDirectory.$cardData[2].'xx/';

            if (!$fs->exists($statusCardDirectory)) {
                $fs->mkdir($statusCardDirectory);
            }

            // Usual status
            $answer = $cardData[3]."\r\n".$cardData[4];
            $filepath = $statusCardDirectory.'/'.$question.'.card';
        }

        extractAndAddLinks($answer, $cardData[6]);

        writeCard($fs, $filepath, $question, $answer, ['response']);
        $progress->advance();
    }

    $progress->finish();
    unset($filename, $filepath, $matches, $progress, $statusesCardDirectory, $familyCardDirectory, $cardData, $question, $answer, $statusCardDirectory);
    $output->writeln('');

    // Extract methods cards
    $output->writeln('<info>Extracting methods data...</info>');

    $filename = 'methods.md';
    $filepath = $tmpDirectory.$filename;

    if (!$fs->exists($filepath)) {
        $output->writeln(sprintf(
            '<error>The file "%s" doesn\'t exists.</error>',
            $filename
        ));

        return;
    }

    preg_match_all(
        '#`([^`]*)` \| "([^"]*)" \| (✔|✘) \| (✔|✘) \| (✔|✘) \| (.*)\n#Uu',
        file_get_contents($filepath),
        $matches,
        PREG_SET_ORDER
    );

    if (0 === count($matches)) {
        $output->writeln('<error>Cannot find the methods in the file.</error>');

        return;
    }

    $output->writeln('<info>Writing methods cards...</info>');
    $progress = new ProgressBar($output, count($matches));
    $progress->start();

    $methodsCardDirectory = $httpCardDirectory.'/methods/';
    if (!$fs->exists($methodsCardDirectory)) {
        $fs->mkdir($methodsCardDirectory);
    }

    foreach ($matches as $cardData) {
        $filepath = $methodsCardDirectory.mb_strtolower($cardData[1]).'.card';
        $question = $cardData[1];
        $answer = sprintf(
            "%s\r\n\r\nSafe: %s\r\nIdempotent: %s\r\nCacheable: %s",
            $cardData[2],
            $cardData[3],
            $cardData[4],
            $cardData[5]
        );

        extractAndAddLinks($answer, $cardData[6]);

        writeCard($fs, $filepath, $question, $answer, ['request']);
        $progress->advance();
    }

    $progress->finish();
    unset($filename, $filepath, $matches, $progress, $methodsCardDirectory, $cardData, $question, $answer);
    $output->writeln('');

    // Extract headers cards
    $output->writeln('<info>Extracting headers data...</info>');

    $filename = 'headers.md';
    $filepath = $tmpDirectory.$filename;

    if (!$fs->exists($filepath)) {
        $output->writeln(sprintf(
            '<error>The file "%s" doesn\'t exists.</error>',
            $filename
        ));

        return;
    }

    $content = file_get_contents($filepath);
    $content = mb_substr($content, 0, mb_strpos($content, 'Less Common (subjective)'));

    preg_match_all(
        '#`([^`]*)` \| "([^"]*)" \| (.*)\n#Uu',
        $content,
        $matches,
        PREG_SET_ORDER
    );

    unset($content);

    if (0 === count($matches)) {
        $output->writeln('<error>Cannot find the headers in the file.</error>');

        return;
    }

    $output->writeln('<info>Writing headers cards...</info>');
    $progress = new ProgressBar($output, count($matches));
    $progress->start();

    $headersCardDirectory = $httpCardDirectory.'/headers/';
    if (!$fs->exists($headersCardDirectory)) {
        $fs->mkdir($headersCardDirectory);
    }

    foreach ($matches as $cardData) {

        if (in_array($cardData[2], ['', 'standard'], true)) {
            continue;
        }

        $filepath = $headersCardDirectory.mb_strtolower($cardData[1]).'.card';
        $question = $cardData[1];
        $answer = $cardData[2];

        extractAndAddLinks($answer, $cardData[3]);

        writeCard($fs, $filepath, $question, $answer, []);

        $progress->advance();
    }

    $progress->finish();
    unset($filename, $filepath, $matches, $progress, $methodsCardDirectory, $cardData, $question, $answer);
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
