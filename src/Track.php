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

        $options->registerOption("message", 'Set entry message', 'm');
        $options->registerOption("interactive", 'Show status after start', 'i');

        $options->registerCommand('start', "Start tracking");
        $options->registerCommand('pause', "Pause tracking");
        $options->registerCommand('finish', "Finish tracking");
        $options->registerCommand('status', "Show status");
        $options->registerCommand('up', "Create db tables");
    }

    protected function main(Options $options)
    {
        $cmd = $options->getCmd();
        if (method_exists($this, $cmd)) {
            $this->$cmd($options);
        } else {
            echo $options->help();
        }
    }

    protected function up()
    {
        DB::schema()->create('entries', function (Blueprint $table) {
            $table->id();
            $table->timestamp('start_at');
            $table->timestamp('finish_at')->nullable();
            $table->string('message')->nullable();
        });
    }

    protected function status(Options $options)
    {
        echo getcwd() . "\n";
        $this->checkCommit();
        $openEntry = $this->getOpenEntry();
        if ($openEntry) {
            $start = Carbon::parse($openEntry->start_at);
            while (true) {
                $diff = Carbon::now()->diff($start);
                $fmtd = $diff->format('%H:%I:%S');
                echo "Tracking: $fmtd\r";
                sleep(1);
            }
        } else {
            echo "Not tracking\n";
        }
    }

    protected function start(Options $options)
    {
        if ($this->getOpenEntry()) {
            echo "Already tracking!\n";
            exit(1);
        }
        $res = DB::table('entries')->insert([
            'start_at' => Carbon::now(),
            'message' => $options->getOpt('m'),
        ]);

        $this->status($options);
    }

    protected function finish()
    {
        $openEntry = $this->getOpenEntry();
        if (!$openEntry) {
            echo "Nothing to finish!\n";
            exit(1);
        }
        DB::table('entries')->where('id', $openEntry->id)->update([
            'finish_at' => Carbon::now(),
        ]);
    }

    protected function getOpenEntry()
    {
        return DB::table('entries')->whereNull('finish_at')->first();
    }

    protected function checkCommit()
    {
        $dir = getcwd();
        $lastCommit = shell_exec("git log -1 --format=\"%ai;%B\"");
        list($timestamp, $message) = explode(';', $lastCommit);
        $last = Carbon::parse($timestamp);
    }
}