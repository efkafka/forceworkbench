<?php
require_once 'restclient/RestClient.php';

class StreamingController {

    private $restApi;
    private $selectedTopic;
    private $pushTopics;
    private $infos;
    private $errors;

    // hard coding API version for getting PushTopics because not available in prior versions
    const restBaseUrl = "/services/data/v22.0";

    function __construct() {
        $this->restApi = WorkbenchContext::get()->getRestDataConnection();

        $this->infos = array();
        $this->errors = array();

        $this->selectedTopic = new PushTopic(
            isset($_REQUEST['pushTopicDmlForm_Id'])         ? $_REQUEST['pushTopicDmlForm_Id']         : null,
            isset($_REQUEST['pushTopicDmlForm_Name'])       ? $_REQUEST['pushTopicDmlForm_Name']       : null,
            isset($_REQUEST['pushTopicDmlForm_ApiVersion']) ? $_REQUEST['pushTopicDmlForm_ApiVersion'] : null,
            isset($_REQUEST['pushTopicDmlForm_Query'])      ? $_REQUEST['pushTopicDmlForm_Query']      : null);

        if (isset($_REQUEST['PUSH_TOPIC_DML_SAVE'])) {
            $this->save();
        }

        if (isset($_REQUEST['PUSH_TOPIC_DML_DELETE'])) {
            $this->delete();
        }

        $this->refresh();
    }

    private function refresh() {
        $pushTopicSoql = "SELECT Id, Name, Query, ApiVersion FROM PushTopic";
        $url = self::restBaseUrl . "/query?" . http_build_query(array("q" => $pushTopicSoql));

        try {
            $queryResponse = $this->restApi->send("GET", $url, null, null, false);
            $this->pushTopics = json_decode($queryResponse->body)->records;
        } catch (Exception $e) {
            $this->errors[] = "Unknown Error Fetching Push Topics:\n" . $e->getMessage();
        }
    }

    private function save() {
        if ($this->selectedTopic->Id != null) {
            $this->dml("PATCH", "Updated", "Updating", "/" . $this->selectedTopic->Id, $this->selectedTopic->toJson(false));
        } else {
            $this->dml("POST", "Created", "Creating", "", $this->selectedTopic->toJson(false));
        }
    }

    private function delete() {
        $this->dml("DELETE", "Deleted", "Deleting", "/".$this->selectedTopic->Id, null);
    }

    private function dml($method, $opPastLabel, $opProgLabel, $urlTail, $data) {
        $headers = array("Content-Type: application/json");
        $url = self::restBaseUrl . "/sobjects/PushTopic" . $urlTail;

        try {
            $response = $this->restApi->send($method, $url, $headers, $data, false);
        } catch (Exception $e) {
            $this->errors[] = "Unknown Error $opProgLabel Push Topic\n:" . $e->getMessage();
            return;
        }

        if (strpos($response->header, "201 Created") > 0 || strpos($response->header, "204 No Content") > 0) {
            $this->infos[] = "Push Topic $opPastLabel Successfully";
        } else {
            $body = json_decode($response->body);
            $this->errors[] = "Error $opProgLabel Push Topic:\n" . $body[0]->message;
        }
    }

    function printMessages() {
        if (count($this->errors) > 0) displayError($this->errors);
        if (count($this->infos) > 0)  displayInfo($this->infos);
    }

    function printPushTopicOptions() {
        print "<option></option>\n";
        print "<option value='". PushTopic::template()->toJson() . "'>--Create New--</option>\n";
        foreach($this->pushTopics as $topic) {
            $topic->Query = htmlspecialchars($topic->Query, ENT_QUOTES);
            $topic->ApiVersion = strpos($topic->ApiVersion, ".") === false ? $topic->ApiVersion.".0" : $topic->ApiVersion;
            $selected = $topic->Name == $this->selectedTopic->Name ? "selected='selected'" : "";
            print "<option value='". json_encode($topic) . "' $selected>" . $topic->Name . "</option>\n";
        }
    }

    function printApiVersionOptions() {
        foreach($GLOBALS['API_VERSIONS'] as $v) {
            print "<option value='$v'>$v</option>\n";
        }
    }
}

class PushTopic {
    public $Id, $Name, $ApiVersion, $Query;

    function __construct($id, $name, $apiVersion, $query) {
        $this->Id = $id;
        $this->Name = $name;
        $this->ApiVersion = $apiVersion;
        $this->Query = $query;
    }

    static function template() {
        return new PushTopic(null, null, WorkbenchContext::get()->getApiVersion(), null);
    }

    static function fromJson($jsonStr) {
        $json = json_decode($jsonStr);

        return new PushTopic($json->Id, $json->Name, $json->ApiVersion, $json->Query);
    }

    function toJson($includeId = true) {
        $clone = $this;

        if (!$includeId) {
            unset($clone->Id);
        }

        return json_encode($clone);
    }
}

?>