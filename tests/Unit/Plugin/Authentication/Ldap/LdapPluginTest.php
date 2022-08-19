<?php

/**
 * @package     Joomla.UnitTest
 * @subpackage  Authentication
 *
 * @copyright   (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Tests\Unit\Plugin\Authentication\Ldap;

use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Authentication\AuthenticationResponse;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Dispatcher;
use Joomla\Tests\Unit\UnitTestCase;
use Symfony\Component\Ldap\Ldap;

/**
 * Test class for Ldap plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  Ldap
 *
 * @testdox     The Ldap plugin
 *
 * @since       __DEPLOY_VERSION__
 */
class LdapPluginTest extends UnitTestCase
{
    public const LDAPPORT = "1389";
    public const SSLPORT = "1636";

    private function getPlugin($options): CMSPlugin
    {
        $type = "authentication";
        $plugin = "ldap";

        // based on loadPluginFromFilesystem in ExtensionManagerTrait
        $path = JPATH_PLUGINS . '/' . $type . '/' . $plugin . '/' . $plugin . '.php';
        require_once $path;

        $dispatcher = new Dispatcher();

        // plugin object: result from DB using PluginHelper::getPlugin
        $pluginobject = [
            'name' => $plugin,
            'params' => json_encode($options),
            'type' => $type
        ];

        return new \PlgAuthenticationLdap($dispatcher, $pluginobject);
    }

    private function acceptCertificates(): void
    {
        ldap_set_option(null, LDAP_OPT_X_TLS_CACERTDIR, '/tmp/certs');
        ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, '/tmp/certs/CA.crt');
    }

    private function getAdminConnection(string $encryption): Ldap
    {
        $admin_options = [
            'host' => "localhost",
            'port' => ($encryption == "ssl" ? self::SSLPORT : self::LDAPPORT),
            'version' => 3,
            'encryption' => $encryption,
            'referrals' => false,
            'debug' => false,
        ];
        $ldap = Ldap::create(
            'ext_ldap',
            $admin_options
        );
        $ldap->bind("cn=admin,cn=config", "configpassword");
        return $ldap;
    }

    private function requireEncryption($encryption): void
    {
        $ldap = $this->getAdminConnection($encryption);
        //TODO configure openldap to require the requested encryption
    }

    /**
     * Setup
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function setUp(): void
    {
        // tests are executed in parallel as root
        // setUp is executed before every test
        $this->default_options = [
            'host' => "localhost",
            'port' => self::LDAPPORT,
            'use_ldapV3' => 1,
            'encryption' => "none",
            'no_referrals' => 0,
            'auth_method' => "bind",
            'base_dn' => "dc=example,dc=org",
            'search_string' => "uid=[search]",
            'users_dn' => "cn=[username],ou=users,dc=example,dc=org",
            'username' => "",
            'password' => "",
            'ldap_fullname' => "cn",
            'ldap_email' => "mail",
            'ldap_uid' => "uid",
            'ldap_debug' => 0
        ];

        $this->default_credentials = [
            'username' => "customuser",
            'password' => "custompassword",
            'secretkey' => null
        ];
    }

    /**
     * Cleanup
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function tearDown(): void
    {
    }

    /**
     * @testdox  can perform an authentication using anynomous search
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function testOnUserAuthenticateAnonymousSearch()
    {
        $options = $this->default_options;
        $options["auth_method"] = "search";
        $options["users_dn"] = "";
        $plugin = $this->getPlugin($options);

        $response = new AuthenticationResponse();
        $plugin->onUserAuthenticate($this->default_credentials, [], $response);
        $this->assertEquals(Authentication::STATUS_SUCCESS, $response->status);
    }

    /**
     * @testdox  can perform an authentication using direct bind
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function testOnUserAuthenticateDirect()
    {
        $this->markTestSkipped("Fix provided in PR #37959");

        $plugin = $this->getPlugin($this->default_options);

        $response = new AuthenticationResponse();
        $plugin->onUserAuthenticate($this->default_credentials, [], $response);
        $this->assertEquals(Authentication::STATUS_SUCCESS, $response->status);
    }

    /**
     * @testdox  can perform an authentication using direct bind with bad credentials
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function testInvalidOnUserAuthenticateDirect()
    {
        $plugin = $this->getPlugin($this->default_options);
        $credentials = $this->default_credentials;
        $credentials['password'] = "wrongpassword";

        $response = new AuthenticationResponse();
        $plugin->onUserAuthenticate($credentials, [], $response);
        $this->assertEquals(Authentication::STATUS_FAILURE, $response->status);
    }

    /**
     * @testdox  can perform an authentication using anynomous search
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function testOnUserAuthenticateAnonymousSearchTLS()
    {
        $options = $this->default_options;
        $options["auth_method"] = "search";
        $options["users_dn"] = "";
        $options["encryption"] = "tls";
        $plugin = $this->getPlugin($options);

        $this->acceptCertificates();
        $this->requireEncryption("tls");

        $response = new AuthenticationResponse();
        $plugin->onUserAuthenticate($this->default_credentials, [], $response);
        $this->assertEquals(Authentication::STATUS_SUCCESS, $response->status);
    }

    /**
     * @testdox  can perform an authentication using anynomous search
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function testOnUserAuthenticateAnonymousSearchSSL()
    {
        $this->markTestSkipped("Fix provided in PR #37962");

        $options = $this->default_options;
        $options["auth_method"] = "search";
        $options["users_dn"] = "";
        $options["encryption"] = "ssl";
        $options["port"] = self::SSLPORT;
        $plugin = $this->getPlugin($options);

        $this->acceptCertificates();
        $this->requireEncryption("ssl");

        $response = new AuthenticationResponse();
        $plugin->onUserAuthenticate($this->default_credentials, [], $response);
        $this->assertEquals(Authentication::STATUS_SUCCESS, $response->status);
    }

    /**
     * @testdox  does log ldap client calls and errors
     * can only be tested if phpunit stderr is redirected/duplicated/configured to a file
     * then, we can check if ldap_ calls are present in that file
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    /*
    public function testOnUserAuthenticateWithDebug()
    {
        $options = $this->default_options;
        $options["ldap_debug"] = 1;
        $plugin = $this->getPlugin($options);

        $response = new AuthenticationResponse();
        $plugin->onUserAuthenticate($this->default_credentials, [], $response);
        $this->assertEquals(Authentication::STATUS_SUCCESS, $response->status);
    }
    */
}
