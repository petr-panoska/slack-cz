<?php

namespace HighlinesBook;

use Nette\Security\Permission;

class Acl extends Permission {

    public function __construct() {
        /* ACL */
        $this->addRole('guest');
        $this->addRole('user');
        $this->addRole('record', 'user');
        $this->addRole('highline', 'record');
        $this->addRole('admin', 'record');

        /*         * **** Zdroje ****** */
        $this->addResource('Admin:rekordy');
        $this->addResource('Admin:highline');
        $this->addResource('Admin:events');

        /*         * **** Pravidla **** */
        $this->allow('record', 'Admin:rekordy', Permission::ALL);
        $this->allow('admin', Permission::ALL, Permission::ALL);
        /* Konec definice ACL */
    }

}
