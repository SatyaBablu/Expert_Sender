<?php
 
namespace App\Services\API;
 
use App\Facades\EspApiAccount;
use App\Facades\Guzzle;
use Exception;
use Log;

class ExpertSenderApi extends EspBaseAPI
{
    const ESP_NAME = "ExpertSender";
    protected $espAccountId;
    private $apiUserName;
    private $espAccount;
    private $apiKey1;

    public function __construct($espAccountId)
    {
        $this->espAccountId = $espAccountId;
        parent::__construct(self::ESP_NAME, $espAccountId);
        $this->espAccount = EspApiAccount::getAccount($this->espAccountId);
        if($this->espAccount != '')
        { 
              $this->apiKey1 = $this->espAccount->key_1;
        }
        else
        {
            Log::error("inavlid $espAccountId is provided to ExpertSenderApi");
            throw new Exception("inavlid espAccountId $espAccountId is provided to ExpertSenderApi");
        }
    }

    public function sendApiRequest() 
    {
         //not used
    }

    public function getContactByEmail($emailAddress)
    {
        $request = '';
        $baseUrl = trim("https://api3.esv2.com/v2/Api/Subscribers?apiKey=$this->apiKey1&email=$emailAddress&option=Full");
        $result = $this->sendRequest('GET',$request,$baseUrl);
        $xml = simplexml_load_string($result);
        $json = json_encode($xml);
        $response = json_decode($json,TRUE);
        return $response;
    }


    public function addContact($contact)
    {
      try
      {
        $baseUrl = trim('https://api3.esv2.com/v2/Api/Subscribers/');
        $apiXmlString = "<ApiRequest xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xs=\"http://www.w3.org/2001/XMLSchema\">\n";
        $apiXmlString .= "<ApiKey>$this->apiKey1</ApiKey>\n";
        $apiXmlString .= "<VerboseErrors>true</VerboseErrors>\n";
        $apiXmlString .=  "<ReturnData>true</ReturnData>\n";
        $apiXmlString .= "<Data xsi:type='Subscriber'>\n";
        $apiXmlString .= "<Mode>AddAndIgnore</Mode>\n";
        $apiXmlString .= "<Force>false</Force>\n";
        $apiXmlString .= "<ListId>".$contact['tag']."</ListId>\n";
        $apiXmlString .= "<Email>".$contact['email']."</Email>\n";
        $apiXmlString .= "<Firstname>".$contact['firstname']."</Firstname>\n";
        $apiXmlString .= "<Lastname>".$contact['lastname']."</Lastname>\n";
        $apiXmlString .= "<Properties>\n";
        foreach($contact['fields'] as $key => $value)
        {
            if($value == '' || $value == NULL)
            {
                continue;
            }
            $apiXmlString .= "<Property>\n";
            $apiXmlString .= "<Id>".$key."</Id>\n";
            $apiXmlString .= "<Value xsi:type=\"xs:string\">".$value."</Value>\n";
            $apiXmlString .= "</Property>\n";
        }
        $apiXmlString .= "</Properties>\n";
        $apiXmlString .= "</Data>\n";
        $apiXmlString .= "</ApiRequest>\n";

        $request = trim($apiXmlString);
        $result = $this->sendRequest('POST',$request,$baseUrl);
        $xml = simplexml_load_string($result);
        $json = json_encode($xml);
        $response = json_decode($json,TRUE);
        Log::info("expertsender reponse for adding email ".$contact['email']);
        Log::info($response);
        if(isset($response['Data']['SubscriberData']))
        {
          $wasAdded = $response['Data']['SubscriberData']['WasAdded'];
          $wasIgnored = $response['Data']['SubscriberData']['WasIgnored'];
          if($wasAdded == 'true')
          {
              return "Success";
          }
          elseif($wasIgnored == 'true')
          {
              return "Duplicate";
          }
        }
        Log::error("expertsender request for error result is ".$request);
        return 'Error';
      }
      catch(Exception $e)
      {
          Log::error("ExpertSenderApi failed at addingcontact   WITH MEASSAGE" . $e->getMessage());
          if(!strpos($e->getResponse()->getBody()->getContents(),'global blacklist'))
          {
              throw $e;
          }
          else{
              return 'Error';
          }
        
      }

    }

    public function sendRequest($method,$request,$baseUrl)
    {
          try
          {
            $client = Guzzle::request($method,$baseUrl , [
                            'body' =>$request,                  
                        ]);
            return $client->getBody()->getContents();
          }
          catch(Exception $e)
          { 
            Log::error("ExpertSenderApi failed at calling API  WITH MEASSAGE" . $e->getMessage());
            throw $e;
          } 
    }

    public function getCampaignId($fromDate,$toDate)
    {
        //function inpkementation
    }

}
