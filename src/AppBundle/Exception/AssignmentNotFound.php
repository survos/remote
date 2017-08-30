<?php
/**
 * Created by PhpStorm.
 * User: pg
 * Date: 24/02/2016
 * Time: 10:20
 */

namespace AppBundle\Exception;


class AssignmentNotFound extends \Exception implements PosseExceptionInterface
{
    private $relatedData = null;

    public function __construct(
        $relatedData = null,
        $message = null,
        $code = 0,
        \Exception $previous = null
    ) {
        $this->relatedData = $relatedData;
        parent::__construct($message, $code, $previous);
    }


    /**
     * @return null
     */
    public function getRelatedData()
    {
        return $this->relatedData;
    }

}