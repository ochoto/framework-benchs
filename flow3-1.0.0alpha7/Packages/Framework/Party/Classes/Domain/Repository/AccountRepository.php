<?php
declare(ENCODING = 'utf-8');
namespace F3\Party\Domain\Repository;

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
 * Repository for accounts
 *
 * @version $Id:$
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class AccountRepository extends \F3\FLOW3\Persistence\Repository {

	/**
	 * Returns the account for a specific authentication provider with the given identitifer
	 *
	 * @param string $accountIdentifier The account identifier
	 * @param string $authenticationProviderName The authentication provider name
	 * @return F3\Party\Domain\Model\Account
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function findByAccountIdentifierAndAuthenticationProviderName($accountIdentifier, $authenticationProviderName) {
		$result = array();

		$query = $this->createQuery();
		$result = $query->matching(
			$query->logicalAnd(
				$query->equals('accountIdentifier', $accountIdentifier),
				$query->equals('authenticationProviderName', $authenticationProviderName)
			)
		)->execute();

		return isset($result[0]) ? $result[0] : FALSE;
	}
}

?>