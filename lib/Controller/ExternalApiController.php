<?php

namespace OCA\OJSXC\Controller;

use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http;
use OCA\OJSXC\Exceptions\UnprocessableException;

class ExternalApiController extends SignatureProtectedApiController
{
	private $userManager;

	private $userSession;

	private $groupManager;

	private $logger;

	public function __construct(
		$appName,
		IRequest $request,
		IUserManager $userManager,
		IUserSession $userSession,
		IGroupManager $groupManager,
		ILogger $logger
	) {
		parent::__construct($appName, $request);

		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
	}

	/**
	* @PublicPage
	* @NoCSRFRequired
	*/
	public function index($operation)
	{
		switch ($operation) {
		 case 'auth':
			return $this->checkPassword(
				$this->request->getParam('username'),
				$this->request->getParam('password'),
				$this->request->getParam('domain')
			);
			break;
		 case 'isuser':
			return $this->isUser(
				$this->request->getParam('username'),
				$this->request->getParam('domain')
			);
			break;
		 case 'sharedroster':
			return $this->sharedRoster(
				$this->request->getParam('username'),
				$this->request->getParam('domain')
			);
			break;
		 default:
			throw new UnprocessableException('Unsupported operation.');
	  }
	}

	/**
	* @PublicPage
	* @NoCSRFRequired
	*/
	public function checkPassword($uid = '', $password = '', $domain = '')
	{
		$currentUser = null;

		$this->logger->info('ExAPI: Check password for user: '.$uid.'@'.$domain);

		if (!empty($password) && !empty($uid)) {
			$loggedIn = false;
			if (!empty($domain) && $this->userManager->userExists($uid . '@' . $domain)) {
				$loggedIn = $this->userSession->login($uid . '@' . $domain, $password);
			}
			if (!$loggedIn && $this->userManager->userExists($uid)) {
				$loggedIn = $this->userSession->login($uid, $password);
			}

			if ($loggedIn === true) {
				$currentUser = $this->userSession->getUser();
			}
		}

		if (!$currentUser) {
			return [
			 'result' => 'noauth',
		  ];
		}

		$data = [];
		$data ['uid'] = $currentUser->getUID();

		return [
		 'result' => 'success',
		 'data' => $data,
	  ];
	}

	/**
	* @PublicPage
	* @NoCSRFRequired
	*/
	public function isUser($uid = '', $domain = '')
	{
		$this->logger->info('ExAPI: Check if "'.$uid.'@'.$domain.'" exists');

		$isUser = false;

		if (!empty($uid)) {
			if (!empty($domain)) {
				$isUser = $this->userManager->userExists($uid . '@' . $domain);
			}
			if (!$isUser) {
				$isUser = $this->userManager->userExists($uid);
			}
		}

		return [
		 'result' => 'success',
		 'data' => [
			'isUser' => $isUser
		 ]
	  ];
	}

	/**
	* @PublicPage
	* @NoCSRFRequired
	*/
	public function sharedRoster($uid = '', $domain = '')
	{
		$currentUser = null;
		if (!empty($uid)) {
			if (!empty($domain) && $this->userManager->userExists($uid . '@' . $domain)) {
				$currentUser = $this->userManager->get($uid . '@' . $domain);
			}
			if (!$currentUser && $this->userManager->userExists($uid)) {
				$currentUser = $this->userManager->get($uid);
			}
		}

		if (!$currentUser) {
			throw new UnprocessableException('Can\'t find user');
		}

		$roster = [];

		$userGroups = $this->groupManager->getUserGroups($currentUser);

		foreach ($userGroups as $userGroup) {
			if (method_exists($userGroup, 'getDisplayName')) {
				$groupName = $userGroup->getDisplayName();
			} else {
				$groupName = $userGroup->getGID();
			}

			foreach ($userGroup->getUsers() as $user) {
				$uidMember = $user->getUID();

				if (!array_key_exists($uidMember, $roster)) {
					$roster[$uidMember] = [
				  'name' => $user->getDisplayName(),
				  'groups' => []
			   ];
				}

				$roster[$uidMember]['groups'][] = $groupName;
			}
		}
		if (empty($roster)) {
			// The user is in no group, return fullname anyway
			$roster[$currentUser->getUID()] = [
					'name'=> $currentUser->getDisplayName()
				];
		}

		return [
		 'result' => 'success',
		 'data' => [
			'sharedRoster' => $roster
		 ]
	  ];
	}
}
