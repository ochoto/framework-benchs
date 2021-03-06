<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\Security\Authorization\Voter;

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
 * Testcase for the ACL voter
 *
 * @version $Id: AclVoterTest.php 3806 2010-02-02 12:12:39Z robert $
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class AclTest extends \F3\Testing\BaseTestCase {

	/**
	 * @test
	 * @category unit
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function voteForJoinPointAbstainsIfNoAccessPrivilegeWasConfigured() {
		$mockRoleAdministrator = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), uniqid('role1'), FALSE);
		$mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('ADMINISTRATOR'));

		$mockRoleCustomer = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), uniqid('role2'), FALSE);
		$mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('CUSTOMER'));

		$mockSecurityContext = $this->getMock('F3\FLOW3\Security\Context', array(), array(), '', FALSE);
		$mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));
		$mockJoinPoint = $this->getMock('F3\FLOW3\AOP\JoinPointInterface');

		$getPrivilegesCallback = function() use (&$mockCustomDenyPrivilege, &$mockCustomDenyPrivilege2) {
			$args = func_get_args();

			if ($args[2] !== 'ACCESS') return array($mockCustomDenyPrivilege, $mockCustomDenyPrivilege2);
			else return array();
		};

		$mockPolicyService = $this->getMock('F3\FLOW3\Security\ACL\PolicyService');
		$mockPolicyService->expects($this->any())->method('getPrivilegesForJoinPoint')->will($this->returnCallback($getPrivilegesCallback));

		$Acl = new Acl($mockPolicyService);
		$this->assertEquals($Acl->voteForJoinPoint($mockSecurityContext, $mockJoinPoint), Acl::VOTE_ABSTAIN, 'The wrong vote was returned!');
	}

	/**
	 * @test
	 * @category unit
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function voteForJoinPointAbstainsIfNoRolesAreAvailable() {
		$mockSecurityContext = $this->getMock('F3\FLOW3\Security\Context', array(), array(), '', FALSE);
		$mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array()));

		$mockJoinPoint = $this->getMock('F3\FLOW3\AOP\JoinPointInterface', array(), array(), '', FALSE);
		$mockPolicyService = $this->getMock('F3\FLOW3\Security\ACL\PolicyService', array(), array(), '', FALSE);

		$Acl = new Acl($mockPolicyService);
		$this->assertEquals($Acl->voteForJoinPoint($mockSecurityContext, $mockJoinPoint), Acl::VOTE_ABSTAIN, 'The wrong vote was returned!');
	}

	/**
	 * @test
	 * @category unit
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function voteForJoinPointDeniesAccessIfAnAccessDenyPrivilegeWasConfiguredForOneOfTheRoles() {
		$mockAccessDenyPrivilege = $this->getMock('F3\FLOW3\Security\ACL\Privilege', array(), array(), '', FALSE);
		$mockAccessDenyPrivilege->expects($this->any())->method('isGrant')->will($this->returnValue(FALSE));
		$mockAccessDenyPrivilege->expects($this->any())->method('isDeny')->will($this->returnValue(TRUE));
		$mockAccessDenyPrivilege->expects($this->any())->method('__toString')->will($this->returnValue('ACCESS'));

		$role1ClassName = uniqid('role1');
		$role2ClassName = uniqid('role2');

		$mockRoleAdministrator = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), $role1ClassName, FALSE);
		$mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('ADMINISTRATOR'));

		$mockRoleCustomer = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), $role2ClassName, FALSE);
		$mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('CUSTOMER'));

		$mockSecurityContext = $this->getMock('F3\FLOW3\Security\Context', array(), array(), '', FALSE);
		$mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));
		$mockJoinPoint = $this->getMock('F3\FLOW3\AOP\JoinPointInterface');

		$getPrivilegesCallback = function() use (&$mockAccessDenyPrivilege, &$role1ClassName) {
			$args = func_get_args();
			if ($args[0] instanceof $role1ClassName) {
				return array($mockAccessDenyPrivilege);
			} else {
				return array();
			}
		};

		$mockPolicyService = $this->getMock('F3\FLOW3\Security\ACL\PolicyService');
		$mockPolicyService->expects($this->any())->method('getPrivilegesForJoinPoint')->will($this->returnCallback($getPrivilegesCallback));

		$Acl = new Acl($mockPolicyService);
		$this->assertEquals($Acl->voteForJoinPoint($mockSecurityContext, $mockJoinPoint), Acl::VOTE_DENY , 'The wrong vote was returned!');
	}

	/**
	 * @test
	 * @category unit
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function voteForJoinPointGrantsAccessIfAnAccessGrantPrivilegeAndNoAccessDenyPrivilegeWasConfigured() {
		$mockAccessGrantPrivilege = $this->getMock('F3\FLOW3\Security\ACL\Privilege', array(), array(), '', FALSE);
		$mockAccessGrantPrivilege->expects($this->any())->method('isGrant')->will($this->returnValue(TRUE));
		$mockAccessGrantPrivilege->expects($this->any())->method('isDeny')->will($this->returnValue(FALSE));
		$mockAccessGrantPrivilege->expects($this->any())->method('__toString')->will($this->returnValue('ACCESS'));

		$role1ClassName = uniqid('role1');
		$role2ClassName = uniqid('role2');

		$mockRoleAdministrator = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), $role1ClassName, FALSE);
		$mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('ADMINISTRATOR'));

		$mockRoleCustomer = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), $role2ClassName, FALSE);
		$mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('CUSTOMER'));

		$mockSecurityContext = $this->getMock('F3\FLOW3\Security\Context', array(), array(), '', FALSE);
		$mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));
		$mockJoinPoint = $this->getMock('F3\FLOW3\AOP\JoinPointInterface');

		$getPrivilegesCallback = function() use (&$mockAccessGrantPrivilege, &$role1ClassName) {
			$args = func_get_args();
			if ($args[0] instanceof $role1ClassName) {
				return array($mockAccessGrantPrivilege);
			} else {
				return array();
			}
		};

		$mockPolicyService = $this->getMock('F3\FLOW3\Security\ACL\PolicyService');
		$mockPolicyService->expects($this->any())->method('getPrivilegesForJoinPoint')->will($this->returnCallback($getPrivilegesCallback));

		$Acl = new Acl($mockPolicyService);
		$this->assertEquals($Acl->voteForJoinPoint($mockSecurityContext, $mockJoinPoint), Acl::VOTE_GRANT , 'The wrong vote was returned!');
	}

	/**
	 * @test
	 * @category unit
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function voteForResourceAbstainsIfNoRolesAreAvailable() {
		$mockSecurityContext = $this->getMock('F3\FLOW3\Security\Context', array(), array(), '', FALSE);
		$mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array()));

		$mockPolicyService = $this->getMock('F3\FLOW3\Security\ACL\PolicyService', array(), array(), '', FALSE);

		$Acl = new Acl($mockPolicyService);
		$this->assertEquals($Acl->voteForResource($mockSecurityContext, 'myResource'), Acl::VOTE_ABSTAIN, 'The wrong vote was returned!');
	}

	/**
	 * @test
	 * @category unit
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function voteForResourceDeniesAccessIfAnAccessDenyPrivilegeWasConfiguredForOneOfTheRoles() {
		$mockAccessDenyPrivilege = $this->getMock('F3\FLOW3\Security\ACL\Privilege', array(), array(), '', FALSE);
		$mockAccessDenyPrivilege->expects($this->any())->method('isGrant')->will($this->returnValue(FALSE));
		$mockAccessDenyPrivilege->expects($this->any())->method('isDeny')->will($this->returnValue(TRUE));
		$mockAccessDenyPrivilege->expects($this->any())->method('__toString')->will($this->returnValue('ACCESS'));

		$role1ClassName = uniqid('role1');
		$role2ClassName = uniqid('role2');

		$mockRoleAdministrator = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), $role1ClassName, FALSE);
		$mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('ADMINISTRATOR'));

		$mockRoleCustomer = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), $role2ClassName, FALSE);
		$mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('CUSTOMER'));

		$mockSecurityContext = $this->getMock('F3\FLOW3\Security\Context', array(), array(), '', FALSE);
		$mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));

		$getPrivilegesCallback = function() use (&$mockAccessDenyPrivilege, &$role1ClassName) {
			$args = func_get_args();
			if ($args[0] instanceof $role1ClassName) {
				return array($mockAccessDenyPrivilege);
			} else {
				return array();
			}
		};

		$mockPolicyService = $this->getMock('F3\FLOW3\Security\ACL\PolicyService');
		$mockPolicyService->expects($this->any())->method('getPrivilegesForResource')->will($this->returnCallback($getPrivilegesCallback));

		$Acl = new Acl($mockPolicyService);
		$this->assertEquals($Acl->voteForResource($mockSecurityContext, 'myResource'), Acl::VOTE_DENY , 'The wrong vote was returned!');
	}

	/**
	 * @test
	 * @category unit
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function voteForResourceGrantsAccessIfAnAccessGrantPrivilegeAndNoAccessDenyPrivilegeWasConfigured() {
		$mockAccessGrantPrivilege = $this->getMock('F3\FLOW3\Security\ACL\Privilege', array(), array(), '', FALSE);
		$mockAccessGrantPrivilege->expects($this->any())->method('isGrant')->will($this->returnValue(TRUE));
		$mockAccessGrantPrivilege->expects($this->any())->method('isDeny')->will($this->returnValue(FALSE));
		$mockAccessGrantPrivilege->expects($this->any())->method('__toString')->will($this->returnValue('ACCESS'));

		$role1ClassName = uniqid('role1');
		$role2ClassName = uniqid('role2');

		$mockRoleAdministrator = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), $role1ClassName, FALSE);
		$mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('ADMINISTRATOR'));

		$mockRoleCustomer = $this->getMock('F3\FLOW3\Security\ACL\Role', array(), array(), $role2ClassName, FALSE);
		$mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('CUSTOMER'));

		$mockSecurityContext = $this->getMock('F3\FLOW3\Security\Context', array(), array(), '', FALSE);
		$mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));

		$getPrivilegesCallback = function() use (&$mockAccessGrantPrivilege, &$role1ClassName) {
			$args = func_get_args();
			if ($args[0] instanceof $role1ClassName) {
				return array($mockAccessGrantPrivilege);
			} else {
				return array();
			}
		};

		$mockPolicyService = $this->getMock('F3\FLOW3\Security\ACL\PolicyService');
		$mockPolicyService->expects($this->any())->method('getPrivilegesForResource')->will($this->returnCallback($getPrivilegesCallback));

		$Acl = new Acl($mockPolicyService);
		$this->assertEquals($Acl->voteForResource($mockSecurityContext, 'myResource'), Acl::VOTE_GRANT , 'The wrong vote was returned!');
	}
}

?>