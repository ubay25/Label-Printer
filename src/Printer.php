<?php

namespace Talal\LabelPrinter;

use Talal\LabelPrinter\Mode\Mode;
use Talal\LabelPrinter\Command\CommandInterface;

class Printer
{
    /**
     * @var Mode $mode
     */
    protected $mode;

    /**
     * Constructor
     *
     * @param Mode $mode
     */
    public function __construct(Mode $mode)
    {
        $this->mode = $mode;
    }

    /**
     * Get the print mode
     *
     * @return Mode   mode
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param CommandInterface $command
     * @return $this
     */
    public function addCommand(CommandInterface $command)
    {
        $this->mode->addCommand($command);

        return $this;
    }

    /**
     * Print the label
     *
     * @return void
     */
    public function printLabel()
    {
        $lastOutput = '';

        foreach ($this->mode->getCommands() as $command) {
            $lastOutput = $command->read();
            $this->mode->sendCommand($lastOutput);
        }

        if (! $this->commandPrintedLabel($lastOutput)) {
            $this->mode->process();
        }
    }

    protected function commandPrintedLabel($output)
    {
        return $output !== '' && substr($output, -1) === chr(26);
    }
}
