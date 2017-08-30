<?php
/**
 * Created by PhpStorm.
 * User: pg
 * Date: 24/02/2016
 * Time: 10:20
 */

namespace AppBundle\Exception;


class LatLonNotFound extends \Exception implements PosseExceptionInterface, AssignmentExceptionInterface
{
    private $assignmentId = null;
    private $relatedData = null;

    public function __construct(
        $assignmentId = null,
        $relatedData = null,
        $message = null,
        $code = 0,
        \Exception $previous = null
    ) {
        $this->assignmentId = $assignmentId;
        $this->relatedData = $relatedData;
        parent::__construct($message, $code, $previous);
    }

    public function getAssignmentId()
    {
        return $this->assignmentId;
    }

    /**
     * @return null
     */
    public function getRelatedData()
    {
        return $this->relatedData;
    }

}