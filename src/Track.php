<?php

namespace karlerss\Traccc;

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use PDO;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Exception;
use splitbrain\phpcli\Options;

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

        // Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();
    }


    protected function setup(Options $options)
    {
        $options->setHelp('PHP time tracker');
        $options->registerOption('version', 'print version', 'v');

        $options->registerCommand('start', "Start tracking");
        $options->registerCommand('pause', "Pause tracking");
        $options->registerCommand('stop', "Finish tracking");
        $options->registerCommand('status', "Show status");
        $options->registerCommand('report', "Show entries");
        $options->registerCommand('up', "Create db tables");

        $options->registerArgument("message", false, 'stop');
        $options->registerOption("interactive", 'Show status after start', 'i', false, 'start');
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
        echo getcwd() . "\n";
        $openEntry = $this->getOpenEntry();
        if ($openEntry) {
            $start = Carbon::parse($openEntry->start_at);
            while (true) {
                $diff = Carbon::now()->diff($start);
                $fmtd = $diff->format('%H:%I:%S');
                echo "Tracking: $fmtd\r";
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

        if ($this->options->getOpt('interactive')) {
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
                echo "Received commit!   \n";
                $this->stop(trim($message));
                sleep(1);
                for ($i = 10; $i >= 0; $i--) {
                    echo "Press Ctrl+C to finish ($i) \r";
                }
                $this->start();
            }
        }
    }

    protected function report()
    {
        var_dump(DB::table('entries')->get());
    }
}