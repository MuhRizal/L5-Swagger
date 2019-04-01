<?php

namespace L5Swagger\Console;

use L5Swagger\Generator;
use Illuminate\Console\Command;

class GenerateDocsCommand extends Command
{
    /**
     * The console command signature .
     *
     * @var string
     */
	protected $signature  = 'l5-swagger:generate {--group= : Documentation group\'s name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate docs';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->option('group')) {
            $this->info('Regenerating documentation group '.$this->option('group').'\'s docs');
            Generator::generateDocs($this->option('group'));
        } else {
            $this->info('Regenerating docs');
			if (config('l5-swagger.api.separated_doc')) {
				foreach (config('l5-swagger.doc_groups', []) as $groupName => $group) {
					Generator::generateDocs($groupName);
				}
			} else {
				Generator::generateDocs();
			}
        }
    }
}
