<?php

declare(strict_types=1);

namespace SsLocal;

use SsLocal\Command\DoctorCommand;
use SsLocal\Command\RunCommand;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('ss-local', '0.1.0');

        $this->add(new RunCommand());
        $this->add(new DoctorCommand());
        $this->setDefaultCommand('run');
    }
}
