<?php

namespace Slack\Models;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="slack_tag")
 */
class Tag extends Object
{

    /**
     * @Id
     * @Column(type="string")
     */
    private $name;

    /**
     * @Column(type="string")
     */
    private $caption;

    /** ManyToMany(targetEntity=Article") */
    private $articles;


    public function getName()
    {
        return $this->name;
    }

    public function getCaption()
    {
        return $this->caption;
    }

    public function getArticles()
    {
        return $this->articles;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setCaption($caption)
    {
        $this->caption = $caption;
    }

    public function setArticles($articles)
    {
        $this->articles = $articles;
    }

}
