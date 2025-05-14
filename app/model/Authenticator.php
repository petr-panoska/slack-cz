<?php

namespace HighlinesBook;

use Nette;
use Nette\Security as NS;
use HighlinesBook\MyAuthorizator;

/**
 * Users authenticator.
 */
class Authenticator extends Nette\Object implements NS\IAuthenticator {

    /**
     * @var Uzivatel
     */
    private $uzivatel;

    /**
     * @param Uzivatel $uzivatel
     */
    public function __construct(Uzivatel $uzivatel) {
        $this->uzivatel = $uzivatel;
    }

    /**
     * Performs an authentication
     * @param  array
     * @return Nette\Security\Identity
     * @throws Nette\Security\AuthenticationException
     */
    public function authenticate(array $credentials) {
        list($username, $password) = $credentials;
        $row = $this->uzivatel->findByName($username);

        if (!$row) {
         throw new NS\AuthenticationException("Uživatel '$username' nenalezen.", self::IDENTITY_NOT_FOUND);
        }

        //if ($row->heslo !== self::calculateHash($password, $row->heslo)) {
        if ($row->heslo !== md5($password)) {
            throw new NS\AuthenticationException("Špatné heslo.", self::INVALID_CREDENTIAL);
        }


        //unset($row->heslo);


        return new NS\Identity($row->id, $row->role , $row->toArray());;
    }

    /**
     * Computes salted password hash.
     * @param  string
     * @param  string
     * @return string
     */
    public static function calculateHash($password, $salt = null) {
        if ($salt === null) {
            $salt = '$2a$07$' . Nette\Utils\Strings::random(32) . '$';
        }
        return crypt($password, $salt);
    }

}
