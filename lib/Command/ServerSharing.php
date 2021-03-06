<?php

namespace OCA\OJSXC\Command;

use OCA\OJSXC\AppInfo\Application;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ServerSharing extends Command
{

	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(
		IConfig $config
	) {
		parent::__construct();
		$this->config = $config;
	}

	protected function configure()
	{
		$this->setName('ojsxc:server_sharing');
		$this->setDescription('Use the Server Sharing settings https://github.com/jsxc/jsxc/wiki/Restrict-chatting-(Nextcloud-internal)');
		$this->addOption('enable');
		$this->addOption('disable');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if (Application::getServerType() !== 'internal') {
			$output->write('This feature is only supported using the internal backend.', true);
			return 0;
		}

		$enable = $input->getOption('enable');
		$disable = $input->getOption('disable');

		if (!$enable && !$disable) {
			if ($this->config->getAppValue('ojsxc', 'use_server_sharing_settings', 'no') === 'yes') {
				$state = 'enabled';
			} else {
				$state = 'disabled';
			}
			$output->write('This feature is currently ' . $state, true);
			return 0;
		}

		if ($enable === $disable) {
			// if both enable and disable passed or none option
			$output->write('Please provide only --enable or --disable', true);
			return 1;
		}


		if ($enable) {
			$this->config->setAppValue('ojsxc', 'use_server_sharing_settings', 'yes');
			$output->write('Successfully enabled.', true);
		}

		if ($disable) {
			$this->config->setAppValue('ojsxc', 'use_server_sharing_settings', 'no');
			$output->write('Successfully disabled.', true);
		}

		return 0;
	}
}
