<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\Security\Aspect;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * The central security aspect, that invokes the security interceptors.
 *
 * @version $Id: RequestDispatchingAspect.php 3643 2010-01-15 14:38:07Z robert $
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @aspect
 */
class RequestDispatchingAspect {

	/**
	 * @var F3\FLOW3\Security\ContextHolderInterface A reference to the security contextholder
	 */
	protected $securityContextHolder;

	/**
	 * @var F3\FLOW3\Security\Auhtorization\FirewallInterface A reference to the firewall
	 */
	protected $firewall;

	/**
	 * @var F3\FLOW3\Security\Channel\RequestHashService The request hash service
	 */
	protected $requestHashService;

	/**
	 * Constructor
	 *
	 * @param F3\FLOW3\Security\ContextHolderInterface $securityContextHolder
	 * @param F3\FLOW3\Security\Authorization\FirewallInterface $firewall
	 * @param F3\FLOW3\Security\Channel\RequestHashService $requestHashService
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function __construct(\F3\FLOW3\Security\ContextHolderInterface $securityContextHolder, \F3\FLOW3\Security\Authorization\FirewallInterface $firewall, \F3\FLOW3\Security\Channel\RequestHashService $requestHashService) {
		$this->securityContextHolder = $securityContextHolder;
		$this->firewall = $firewall;
		$this->requestHashService = $requestHashService;
	}

	/**
	 * Advices the dispatch method to initialize the security framework.
	 *
	 * @around method(F3\FLOW3\MVC\Dispatcher->dispatch()) && setting(FLOW3.security.enable)
	 * @param F3\FLOW3\AOP\JoinPointInterface $joinPoint The current joinpoint
	 * @return mixed Result of the advice chain
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function initializeSecurity(\F3\FLOW3\AOP\JoinPointInterface $joinPoint) {
		$request = $joinPoint->getMethodArgument('request');
		$this->securityContextHolder->initializeContext($request);
		return $joinPoint->getAdviceChain()->proceed($joinPoint);
	}

	/**
	 * Advices the dispatch method so that illegal requests are blocked before invoking
	 * any controller.
	 *
	 * @around method(F3\FLOW3\MVC\Dispatcher->dispatch()) && setting(FLOW3.security.enable)
	 * @param F3\FLOW3\AOP\JoinPointInterface $joinPoint The current joinpoint
	 * @return mixed Result of the advice chain
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function blockIllegalRequests(\F3\FLOW3\AOP\JoinPointInterface $joinPoint) {
		$request = $joinPoint->getMethodArgument('request');
		$this->firewall->blockIllegalRequests($request);
		return $joinPoint->getAdviceChain()->proceed($joinPoint);
	}

	/**
	 * Advices the dispatch method to check the HMAC.
	 *
	 * @around method(F3\FLOW3\MVC\Dispatcher->dispatch()) && setting(FLOW3.security.enable)
	 * @param F3\FLOW3\AOP\JoinPointInterface $joinPoint The current joinpoint
	 * @return mixed Result of the advice chain
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 */
	public function checkRequestHash(\F3\FLOW3\AOP\JoinPointInterface $joinPoint) {
		$request = $joinPoint->getMethodArgument('request');
		if ($request instanceof \F3\FLOW3\MVC\Web\Request) {
			$this->requestHashService->verifyRequest($request);
		}
		return $joinPoint->getAdviceChain()->proceed($joinPoint);
	}

	/**
	 * Catches AuthenticationRequired Exceptions and tries to call an authentication entry point,
	 * if available.
	 *
	 * @afterthrowing method(F3\FLOW3\MVC\Dispatcher->dispatch()) && setting(FLOW3.security.enable)
	 * @param F3\FLOW3\AOP\JoinPointInterface $joinPoint The current joinpoint
	 * @return void
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function forwardAuthenticationRequiredExceptionsToAnAuthenticationEntryPoint(\F3\FLOW3\AOP\JoinPointInterface $joinPoint) {
		$exception = $joinPoint->getException();
		$request = $joinPoint->getMethodArgument('request');
		$response = $joinPoint->getMethodArgument('response');

		if (!$exception instanceof \F3\FLOW3\Security\Exception\AuthenticationRequiredException) throw $exception;

		$entryPointFound = FALSE;
		foreach ($this->securityContextHolder->getContext()->getAuthenticationTokens() as $token) {
			$entryPoint = $token->getAuthenticationEntryPoint();

			if ($entryPoint !== NULL && $entryPoint->canForward($request)) {
				$entryPointFound = TRUE;
				$entryPoint->startAuthentication($request, $response);
			}
		}
		if ($entryPointFound === FALSE) throw $exception;
	}
}
?>