<?php

namespace Baril\Bonsai\Console;

use Baril\Bonsai\Console\Concerns\InteractsWithTree;
use Illuminate\Console\Command;

class ShowTreeCommand extends Command
{
    use InteractsWithTree;

    protected $signature = 'bonsai:show {model : The model class}
        {--label= : The property to use as label}
        {--depth= : The depth limit}';
    protected $description = 'Outputs the content of the table in tree form';

    protected $model;
    protected $label;
    protected $currentDepth = 0;
    protected $flags = [];

    public function handle()
    {
        $this->model = $this->input->getArgument('model');
        $this->label = $this->input->getOption('label');

        $this->checkModel($this->model);

        $this->showTree($this->input->getOption('depth'));
    }

    protected function showTree($depth)
    {
        $tree = $this->model::getTree($depth);
        $count = count($tree);
        foreach ($tree as $k => $node) {
            $this->showNode($node, $k == $count - 1);
        }
    }

    protected function showNode($node, $isLast)
    {
        $this->flags[$this->currentDepth] = $isLast;
        $line = '';
        for ($i = 0; $i < $this->currentDepth; $i++) {
            $line .= $this->flags[$i] ? "   " : "\u{2502}  ";
        }
        $line .= ($isLast ? "\u{2514}" : "\u{251C}") . "\u{2500}<info> #" . $node->getKey() . '</info>';
        if ($this->label) {
            $line .= ': ' . $node->{$this->label};
        }
        $this->line($line);
        if ($node->relationLoaded('children')) {
            $this->currentDepth++;
            $subCount = count($node->children);
            foreach ($node->children as $k => $child) {
                $this->showNode($child, $k == $subCount - 1);
            }
            $this->currentDepth--;
        }
    }
}
