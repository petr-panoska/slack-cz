<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

namespace HighlinesBook;

/**
 * Description of Uzivatel
 *
 * @author Vejvis
 */
class Uzivatel extends \HighlinesBook\Table {

    /** @var string */
    protected $tableName = 'uzivatel';

    /** @var int */
    protected $id;

    /** @var string */
    protected $nick;

    /** @var string */
    protected $heslo;

    /** @var string */
    protected $jmeno;

    /** @var string */
    protected $prijmeni;

    /** @var int */
    protected $rok_nar;

    /** @var string */
    protected $telefon;

    /** @var string */
    protected $mesto;

    public function findByName($nick) {
        return $this->findAll()->where('nick', $nick)->fetch();
    }

    public function setPassword($id, $password) {
        $this->getTable()->where(array('id' => $id))->update(array(
            'heslo' => Authenticator::calculateHash($password)
        ));
    }

    /**
     * @param string $nick
     * @param string $jmeno
     * @param string $prijmeni
     * @param string $heslo
     * @param int $rok_nar
     * @param string $email
     * @param string $telefon
     * @param string $mesto
     * @param string $role
     *
     * @return \Nette\Database\Table\ActiveRow
     */
    public function registerUser($nick, $jmeno, $prijmeni, $heslo, $rok_nar, $email, $telefon, $mesto, $role) {
        return $this->getTable()->insert(array(
                    'nick' => $nick,
                    'jmeno' => $jmeno,
                    'prijmeni' => $prijmeni,
                    'rok_nar' => $rok_nar,
                    'heslo' => $heslo,
                    'email' => $email,
                    'telefon' => $telefon,
                    'mesto' => $mesto,
                    'role' => $role
                ));
    }

}
