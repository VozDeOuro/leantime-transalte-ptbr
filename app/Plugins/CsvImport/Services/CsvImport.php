<?php

namespace Leantime\Plugins\CsvImport\Services;

use Leantime\Core\Frontcontroller;
use Leantime\Domain\Connector\Models\Entity;
use Leantime\Domain\Connector\Models\Provider;
use Leantime\Domain\Connector\Services\Connector\providerIntegration;

class CsvImport extends Provider implements ProviderIntegration
{
    private array $fields;

    public function __construct()
    {


        $this->id = "csv_importer";
        $this->name = "CSV Import";
        $this->image = "/dist/images/doc.png";

        $this->methods[] = "import";

        //CSVs can be anyting but are always one file.
        $this->entities = array(
            "default" => array(
                "name" => "Sheet",
                "fields" => array(),
        ),
        );
    }

    //Logic to connect to provider goes here.
    //Needs to manage new connection as well as existing connections.
    //Should return bool so we can drive logic in the frontend.
    public function connect()
    {


        //Connection done. Send to next step.
        //May just want to add a nextStep() method to provider model or so.
        Frontcontroller::redirect(BASE_URL . "/connector/integration?provider=" . $this->id . "#/csvImport/upload");
    }

    //Sync the entities from the db
    public function sync(Entity $Entity)
    {

        return true;
    }

    //Get available fields
    public function getFields()
    {
        return $_SESSION['csvImporter']['headers'] ?? array();
    }

    public function setFields(array $fields)
    {

        //$_SESSION['csvImporter']['headers'] = json_encode($fields);
    }

    //Get available entities
    public function getEntities()
    {
        return $this->entities;
    }

    public function getValues(Entity $Entity)
    {
    }
}
