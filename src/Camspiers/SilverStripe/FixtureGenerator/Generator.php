<?php

namespace Camspiers\SilverStripe\FixtureGenerator;

use DataObject;
use DataObjectSet;

class Generator
{
    /**
     * @param DumperInterface $dumper
     */
    public function __construct(DumperInterface $dumper)
    {
        $this->dumper = $dumper;
    }
    /**
     * @param DataObjectSet $dataObjectSet
     * @return mixed
     */
    public function process(DataObjectSet $dataObjectSet)
    {
        $map = array();
        if ($dataObjectSet->Count() > 0) {
            foreach ($dataObjectSet as $dataObject) {
                if (!$this->hasDataObject($dataObject, $map)) {
                    $map = $this->generateFromDataObject($dataObject, $map);
                }
            }
        }

        return $this->dumper->dump(array_reverse($map, true));
    }
    /**
     * @param DataObject $dataObject
     * @param array      $map
     * @return array
     */
    private function generateFromDataObject(DataObject $dataObject, array $map = array())
    {
        $className = $dataObject->ClassName;
        $id = $dataObject->ID;
        // If we haven't encountered a object of ClassName, add ClassName to data
        if (!isset($map[$className])) {
            $map[$className] = array();
        }
        // Add the object to the
        $map[$className][$id] = $this->getMap($dataObject);
        // Loop over the has one of this object
        if ($hasOnes = $dataObject->has_one()) {
            foreach ($hasOnes as $relName => $relClass) {
                // Get the dataobject from the relation
                $hasOne = $dataObject->$relName();
                // Only process it if it exists
                if ($hasOne->exists() && !$this->hasDataObject($hasOne, $map)) {
                    // Recursively generate a map for this object
                    $map = $this->generateFromDataObject($hasOne, $map);
                    // Add the relation to the current dataobjects map
                    $map[$className][$id] = array_merge(
                        $map[$className][$id],
                        array(
                            $relName => "=>$relClass." . $hasOne->ID
                        )
                    );
                }
            }
        }
        // Loop over the has many relations
        if ($hasManys = $dataObject->has_many()) {
            foreach ($hasManys as $relName => $relClass) {
                // Get the dataobjects from the relation
                $items = $dataObject->$relName();
                // If any exist
                if ($items instanceof DataObjectSet && count($items) > 0) {
                    // Loops of each dataobject
                    foreach ($items as $hasMany) {
                        // Only process it if it exists
                        if ($hasMany->exists() && !$this->hasDataObject($hasMany, $map)) {
                            // Recursively generate a map for this object
                            $map = $this->generateFromDataObject($hasMany, $map);
                            // Add the relation to the original objects map
                            if (!isset($map[$className][$id][$relName])) {
                                $map[$className][$id] = array_merge(
                                    $map[$className][$id],
                                    array(
                                        $relName => "=>$relClass." . $hasMany->ID
                                    )
                                );
                            } else {
                                $map[$className][$id][$relName] .= ", =>$relClass." . $hasMany->ID;
                            }
                        }
                    }
                }
            }
        }

        return $map;
    }
    /**
     * @param DataObject $dataObject
     * @param array      $map
     * @return bool
     */
    private function hasDataObject(DataObject $dataObject, array $map = array())
    {
        return isset($map[$dataObject->ClassName][$dataObject->ID]);
    }
    /**
     * @param DataObject $dataObject
     * @return array
     */
    private function getMap(DataObject $dataObject)
    {
        $map = $dataObject->toMap();
        unset($map['Created']);
        unset($map['LastEdited']);
        unset($map['RecordClassName']);
        unset($map['ClassName']);
        unset($map['ID']);
        foreach ($map as $key => $value) {
            if (substr($key, -2) == 'ID') {
                unset($map[$key]);
            }
        }

        return $map;
    }
}
