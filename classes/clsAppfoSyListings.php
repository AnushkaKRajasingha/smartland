<?php
/**
 * User: anushkar
 * Date: 2/26/19
 * Time: 10:13 AM
 */

namespace appfoliosync;
class clsAppfoSyListings
{
    private $wrapper ;
    public $appfoliolistings;
    function __construct()
    {
    try{
        $this->wrapper = new clsAppfoSyListingWrapper();
        $this->appfoliolistings = array();
        add_action("wp_ajax_doappfosync", array(
            $this,
            "doappfosync"
        ));
        add_action("wp_ajax_doappfosyncsingle", array(
            $this,
            "doappfosyncsingle"
        ));

        add_action('appfosy_event', array(
            $this,
            "doappfosync"
        ));
    }
    catch(\Exception $e){
        $_appfosyncLogger = new clsAppfoSyLogWriter();
        $_appfosyncLogger->warning($e->getMessage());
    }

    }

    public function scrapeAppfolistings(){
        try{
            $listids = $this->wrapper->scrapeListingList(); //return;
            foreach ($listids as $listingid => $listitem){
                $this->appfoliolistings[$listingid] = array( $this->wrapper->listingDetail($listingid), $listitem[3]);
            }
        }
        catch(\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }
    }

    public function importAppfolioListing(){
        try{
            $this->scrapeAppfolistings();

            if($this->appfoliolistings) {
                $listing_posttype = get_option( APPFOSYPERFIX . 'listing_posttype' );
                $listing_tag_house_id = get_option( APPFOSYPERFIX . 'house_catid' );
                $listing_tag_unit_id = get_option( APPFOSYPERFIX . 'unit_catid' );

                $counter = 0 ;
                foreach ($this->appfoliolistings as $listid => $listitem) {
                    //$listitem[0] = new clsAppfolioListItem();

                    try{
                        $post_meta = array(
                            'content' => $listitem[0]->description,
                            '_wre_listing_mls' => $listid,
                            '_wre_listing_price' => $listitem[0]->amount,
                            '_wre_listing_status' => $listitem[0]->status,
                            '_wre_listing_purpose' => 'Rent',
                            '_wre_listing_bedrooms' => $listitem[0]->bedrooms,
                            '_wre_listing_bathrooms' => $listitem[0]->bathrooms,
                            '_wre_listing_street_number' => $listitem[0]->addressLine1,
                            '_wre_listing_route' => $listitem[0]->addressLine2,
                            '_wre_listing_displayed_address' =>$listitem[0]->fullAddress,
                            '_wre_listing_city' => $listitem[0]->city,
                            '_wre_listing_state' => $listitem[0]->state,
                            '_wre_listing_country' => $listitem[0]->country,
                            '_wre_listing_lat' => $listitem[0]->location_lat,
                            '_wre_listing_lng' => $listitem[0]->location_lng,
                            '_wre_listing_zip' => $listitem[0]->zip

                        );

                        $tags = $listitem[0]->propertyType == 'House' ? array($listing_tag_house_id) : array($listing_tag_unit_id);

                        $wp_appfo_post = array(
                            'post_content' => $listitem[0]->description,
                            'post_title' => $listitem[0]->title,
                            'post_status' => 'publish',
                            'post_type' => $listing_posttype,
                            //'tags_input' => $listitem[0]->propertyType == 'House' ? array($listing_tag_house_id) : array($listing_tag_unit_id),
                            'meta_input' => $post_meta
                        );

                        if($listitem[1]){
                            $wp_appfo_post['ID'] = $listitem[1]->ID;// var_dump($wp_appfo_post);
                            $listitem[0]->ID =  wp_update_post( $wp_appfo_post );
                        }
                        else{
                            $listitem[0]->ID =  wp_insert_post( $wp_appfo_post );
                            $listitem[0]->createGallery();
                        }

                        wp_set_post_terms( $listitem[0]->ID,$tags,'listing-type');

                    }
                    catch(\Exception $ee){
                        $_appfosyncLogger = new clsAppfoSyLogWriter();
                        $_appfosyncLogger->warning($ee->getMessage());
                    }
                    $counter++;
                }
            }
            else{
                $_appfosyncLogger = new clsAppfoSyLogWriter();
                $_appfosyncLogger->warning('No Listing Items');
            }

            return $counter;
        }
        catch(\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }
    }

    public function doappfosync(){
        try{
            $counter = $this->importAppfolioListing();
           echo json_encode(array(
                'Message' => $counter.' Listing(s) have been imported',
               'Listings' => $this->appfoliolistings
           ));
            exit(0);
        }
        catch(\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }
    }

    public function doappfosyncsingle(){
        try{
            $id = 'b2289c40-16be-46e0-a660-6d49a6335484';

            $item =  $this->wrapper->listingDetail($id);

            echo json_encode(array(
                'Message' => 'testing doappfosyncsingle',
                'ListingItem' => $item
            ));
            exit(0);
        }
        catch(\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }
    }
}


class clsAppfolioListItem{

    public $ID;
    public $listingId; //ID (unique identifier)  -  can be generated
    public $propertyType; //ProperyType (1:Home/ 2:Apartment) -  Unable to identify
    public $addressLine1; //Address1 - Can be filled
    public $addressLine2; //Address2  - Can be filled
    public $city; //City -  Can be filled
    public $state; //State -  Can be filled
    public $zip; //Zip -  Can be filled
    public $country; // Country
    public $mapLink; //MapLink -  Can be filled
    public $bedrooms; //Bedrooms -  Can be filled ( only if the are in same format as right now)
    public $bathrooms; //Bathrooms -  Can be filled ( only if the are in same format as right now)
    public $area; //SquareFeet -  Can be filled
    public $title; //DescriptionTitle -  Can be filled
    public $description; //Description - -  Can be filled , but it will be the whole text
    public $amount; //RentAmount -  Can be filled
    public $applicationFee; //ApplicationFee - Will not be able to filter
    public $secDeposit; //SecurityDeposit - Will not be able to filter
    public $status; //Status (Available/NotAvailable) -  Can be filled
    public $petAllowed; //PetsAllowed (Yes No) -  Can be filled
    public $applyNowUrl; //ApplyNowURL -  Can be filled
    public $agentName; //LeasingAgentName - Will not be able to filter
    public $agentPhone; //LeasingAgentPhone - Will not be able to filter
    public $agentEmail ; //LeasingAgentEmail - Will not be able to filter
    public $appearedOn; //ListingAppearedOn (datetime) - Will not be able to filter
    public $updatedOn ; // ListingUpdatedOn(datetime) - Will not be able to filter
    public $fullAddress; // Display address
    public $location_lat; //
    public $location_lng; //

    public $images;

    function __construct()
    {
        $this->updatedOn = time();
        $this->country = 'USA';
        $this->images = array();
    }

    function createGallery(){
        try{

        }
        catch(\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }
    }
}

class clsAppfoSyListingWrapper{

    private $needle;
    private $domain;
    private $limited;

    function __construct()
    {
        // $list =  $this->scrapeListingList();

        $this->needle = '/listings/detail/';
        $this->domain = 'https://smartland.appfolio.com';
        $this->limited = 1;

        //$item  = $this->listingDetail("b2289c40-16be-46e0-a660-6d49a6335484");
    }


    public function scrapeListingList(){
        try{
            require_once 'shmAppfosync.php';
            $listingUrl = get_option( APPFOSYPERFIX . 'listing_url' );

            if($listingUrl === '#') { throw new \Exception(get_option( 'invalid URL - '.$listingUrl )); exit();}

            $html   = file_get_html($listingUrl);
            $result = parse_url($listingUrl);
            $domain = $result['scheme'] . "://" . $result['host'];

            $listingsList = $html->find('a.btn.js-link-to-detail[href^="'.$this->needle.'"]');
            $listingsList_array = array();
            $counter = 0; $_limit = 1;
            if ($listingsList) {
                foreach ($listingsList as $element) {
                    $listingId = str_replace($this->needle,'',$element->href,$counter);
                    $_post = $this->getPostbyListingid($listingId);
                    $listingsList_array[$listingId] = [$domain,$element->href,$counter,$_post];
                  //  if($_limit >= $this->limited) break;
                    $_limit++;
                }
            }
            return $listingsList_array;
            //  throw new \Exception(get_option( APPFOSYPERFIX . 'listing_url' ));
        }
        catch (\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }
    }

    private function getPostbyListingid($listingid){
        try{
            $listing_posttype = get_option( APPFOSYPERFIX . 'listing_posttype' );
            $listing_query = new \WP_Query( array('post_type' => $listing_posttype, 'meta_query' => array( array( 'key' => '_wre_listing_mls', 'value' => $listingid ) )) );
            if($listing_query->have_posts()){
                global $post;
                $listing_query->the_post();
                $_post = $post;
                wp_reset_postdata();
                return $_post;
            }
            else{
                return false;
            }
            wp_reset_postdata();
        }
        catch (\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }
    }

    private function extractFromAddress($address_components, $type){
        foreach ( $address_components as $component){
            //var_dump($component); echo '<hr/>';
               if(in_array($type , $component->types)) return $component->long_name;

        }
        return "";
    }

    public function listingDetail($listingId ,$url = '#'){
        try{
            require_once 'shmAppfosync.php';
            if($url === '#') $url = $this->domain . $this->needle;
            $listingUrl = $url.$listingId;


            $html   = file_get_html($listingUrl);

            $listItem = new clsAppfolioListItem();
            //Listing ID
            $listItem->listingId = $listingId;

            //Title
            $_title = $html->find('title');
            $listItem->title = $_title[0]->innertext;
            //echo $listItem->title;

            // Description
            $desc = $html->find('.listing-detail p.listing-detail__description');
            $listItem->description = $desc[0]->innertext;

            // Property type
            $listItem->propertyType = "House";
            if(strpos(strtolower( $listItem->description),'unit') || strpos(strtolower( $listItem->description),'apartment')){
                $listItem->propertyType = "Apartment";
            }

            // Address
            $address = $listItem->title;

            $gapi_key = get_option( APPFOSYPERFIX . 'gapi' );

            $prepAddr = str_replace(' ','+',$address);
            $geocode=file_get_contents('https://maps.google.com/maps/api/geocode/json?key='.$gapi_key.'&address='.$prepAddr);
            //echo $geocode;
            $address = json_decode($geocode);


            $listItem->fullAddress = $address->results[0]->formatted_address;

            $listItem->addressLine1 =  $this->extractFromAddress($address->results[0]->address_components,'street_number')." ";
            $listItem->addressLine1 .=  $this->extractFromAddress($address->results[0]->address_components,'route')." ";
            $listItem->addressLine1 .=  $this->extractFromAddress($address->results[0]->address_components,'subpremise');
            $listItem->addressLine2 = $this->extractFromAddress($address->results[0]->address_components,'locality')." ";
            $listItem->city = $this->extractFromAddress($address->results[0]->address_components,'locality')." ";

            $listItem->state = $this->extractFromAddress($address->results[0]->address_components,'administrative_area_level_1');
            $listItem->zip = $this->extractFromAddress($address->results[0]->address_components,'postal_code');
            $listItem->country =  $this->extractFromAddress($address->results[0]->address_components,'country');

            $listItem->location_lat = $address->results[0]->geometry->location->lat;
            $listItem->location_lng = $address->results[0]->geometry->location->lng;

            // maplink
            $maplink = $html->find('a.header__title__map-link');
            $listItem->mapLink = $maplink[0]->href;

            // bedrooms
            $bedbath = $html->find('div.sidebar__beds-baths');
            $bedbath = $bedbath[0]->innertext;
            $listItem->bedrooms = substr($bedbath,0,strpos($bedbath," "));
            $listItem->bathrooms = substr($bedbath,strpos($bedbath,"/ ")); $listItem->bathrooms  = substr($listItem->bathrooms ,2,strpos($listItem->bathrooms ," ")+2);

            // Area
            $header__summary = $html->find('p.header__summary.js-show-summary');
            $area = $header__summary[0]->innertext;
            $area = substr($area,strpos($area,"ba, ")); $area = substr($area,4,strpos($area," Sq.")-4);
            $listItem->area = str_replace(",","",$area); //  $area;

            // Title
            $_title = $html->find('h2.listing-detail__title');
            $listItem->title = $_title[0]->innertext;

            // status
            $listItem->status = trim(substr($header__summary[0]->innertext,strpos($header__summary[0]->innertext,"| ")+2));

            // Amount
            //$list = $html->find('div.grid > div.grid__large-6 li.list__item');
            //foreach ($list as $it){  echo '<pre>';
            //    var_dump($it->innertext);
            //   echo '</pre>';}
            $amount = substr($listItem->description,strpos($listItem->description,"Rent: $")+7); $amount = substr($amount,0,strpos($amount,"/mo")-3);
            $listItem->amount = str_replace(",","",$amount);
            $amount = substr($listItem->description,strpos($listItem->description,"Deposit: $")+10); $amount = substr($amount,0,strpos($amount," "));
            $listItem->secDeposit = str_replace(",","",$amount);
            $amount = substr($listItem->description,strpos($listItem->description,"Fee: $")+6); $amount = substr($amount,0,strpos($amount," "));
            $listItem->applicationFee = str_replace(",","",$amount);
           // $amount = $list[0]->innertext ; $amount = substr($amount,strpos($amount,"$")+1); $listItem->amount = str_replace(",","",$amount);
           // $amount = $list[1]->innertext ; $amount = substr($amount,strpos($amount,"$")+1); $listItem->applicationFee = str_replace(",","",$amount);
          //  $amount = $list[2]->innertext ; $amount = substr($amount,strpos($amount,"$")+1); $listItem->secDeposit = str_replace(",","",$amount);

            // pet policy
            $list = $html->find('div.grid > div.grid__large-6.grid__medium-6.grid__small-12 li.list__item.js-pet-policy-item');
            $listItem->petAllowed = array();
            foreach ($list as $item){
              array_push($listItem->petAllowed,$item->innertext);
            }

            // applynow url
            $listItem->applyNowUrl = "https://smartland.appfolio.com/listings/rental_applications/new?listable_uid=".$listItem->listingId;


            // Agent details
            $agent = substr($listItem->description,strpos($listItem->description,"Leasing Agent: ")+15); $agent = substr($agent,0,strpos($agent,"<br>")-4);
            $listItem->agentName = substr($agent,0,strpos($agent," -"));
            $listItem->agentPhone = substr($agent,strpos($agent,"- ")+2);$listItem->agentPhone = substr($listItem->agentPhone,0,strpos($listItem->agentPhone," |"));
            $listItem->agentEmail = trim(substr($agent,strpos($agent,"| ")+2));

            // Images
            $_images = $html->find('img.gallery__small-image');
            foreach($_images as $image){
                $listItem->images[] = $image->src;
            }

           return $listItem;

        }
        catch (\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }

    }

}