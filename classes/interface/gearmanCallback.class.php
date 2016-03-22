<?php

/**
 * Any class that implements this can be referenced as a queue job reciever with the setJob()
 * method being the fetch() portion of a gearman queue worker cycle.
 *
 * @package framework
 * @subpackage interface
 */
interface interface_gearmanCallback
{
   public static function setJob(GearmanJob $job);
}
