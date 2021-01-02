<?php

namespace karlerss\Traccc;

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;
use splitbrain\phpcli\TableFormatter;

class Track extends CLI
{

    public function __construct($autocatch = true)
    {
        parent::__construct($autocatch);

        $this->connect();
    }

    protected function connect()
    {
        $capsule = new DB;

        $capsule->addConnection([
            'driver' => 'sqlite',
            'host' => '',
            'database' => __DIR__ . "/../db.sqlite",
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);

        $capsule->setAsGlobal();
    }


    protected function setup(Options $options)
    {
        $options->setHelp('A CLI time tracker with git commit watcher');
        $options->registerOption('version', 'print version', 'v');

        $options->registerCommand('start', "Start tracking");
        $options->registerCommand('stop', "Finish tracking");
        $options->registerCommand('status', "Show status");
        $options->registerCommand('report', "Show entries");
        $options->registerCommand('up', "Create db tables");

        $options->registerArgument("message", 'Message', false, 'stop');
        $options->registerOption("watch", 'Watch for git commits in working directory', 'w', false, 'start');
    }

    protected function main(Options $options)
    {
        $cmd = $options->getCmd();
        if (method_exists($this, $cmd)) {
            $this->$cmd();
        } else {
            echo $options->help();
        }
    }

    protected function up()
    {
//        DB::schema()->dropAllTables();

        DB::schema()->create('entries', function (Blueprint $table) {
            $table->id();
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->string('message')->nullable();
        });
    }

    protected function status()
    {
        $openEntry = $this->getOpenEntry();
        if ($openEntry) {
            $start = Carbon::parse($openEntry->start_at);
            while (true) {
                $diff = Carbon::now()->diff($start);
                $fmtd = $diff->format('%H:%I:%S');
                echo "\033[32mTracking: $fmtd\r";
                sleep(1);
                $this->checkCommit();
            }
        } else {
            echo "Not tracking\n";
        }
    }

    protected function start()
    {
        if ($this->getOpenEntry()) {
            echo "Already tracking!\n";
            exit(1);
        }
        $res = DB::table('entries')->insert([
            'start_at' => Carbon::now(),
            'message' => $this->options->getOpt('m'),
        ]);

        if ($this->options->getOpt('watch')) {
            $this->status();
        }
    }

    protected function stop(?string $message = null)
    {
        $msg = $message ?? $this->options->getArgs()[0] ?? null;

        echo "Stopping: $msg \n";

        $openEntry = $this->getOpenEntry();

        if (!$openEntry) {
            echo "Nothing to finish!\n";
            exit(1);
        }

        DB::table('entries')->where('id', $openEntry->id)->update([
            'end_at' => Carbon::now(),
            'message' => $msg,
        ]);
    }

    protected function getOpenEntry()
    {
        return DB::table('entries')->whereNull('end_at')->first();
    }

    private function checkCommit()
    {
        $dir = getcwd();
        $lastCommit = shell_exec("git log -1 --format=\"%ai;%B\"");
        list($timestamp, $message) = explode(';', $lastCommit);
        $last = Carbon::parse($timestamp);
        $openEntry = $this->getOpenEntry();
        if ($openEntry) {
            $started = Carbon::parse($openEntry->start_at);
            if ($last->isAfter($started)) {
                echo "Received commit!   \n\n";
                $this->stop(trim($message));
                sleep(1);
                for ($i = 10; $i >= 0; $i--) {
                    echo "Press Ctrl+C to finish ($i) \r";
                    sleep(1);
                }
                $this->start();
            }
        }
    }

    protected function report()
    {
        $entries = DB::table('entries')->get();
        $tf = new TableFormatter($this->colors);
        $tf->setBorder(' | '); // nice border between colmns

        // show a header

        $widths = ['5%', '*', '10%', '20%', '20%'];
        echo $tf->format(
            $widths,
            ['ID', 'message', 'duration (h)', 'start', 'end']
        );
        foreach ($entries as $entry) {
            $start = Carbon::parse($entry->start_at);
            $end = Carbon::parse($entry->end_at);
            $duration = $start->diffInSeconds($end, true);
            echo $tf->format($widths, [
                $entry->id,
                $entry->message,
                round($duration / 60 / 60, 5),
                $start->setTimezone('Europe/Tallinn'),
                $end->setTimezone('Europe/Tallinn'),
            ]);
        }
    }
}