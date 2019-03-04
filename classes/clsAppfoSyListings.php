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

                    $_appfosyncLogger = new clsAppfoSyLogWriter();

                    try{
                        $username = substr($listitem[0]->agentEmail,0,strpos($listitem[0]->agentEmail,'@'));
                        $user_id = username_exists( $username );
                        if ( !$user_id and email_exists($listitem[0]->agentEmail) == false ) {
                            $random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
                            $user_id = wp_create_user( $username, $random_password, $listitem[0]->agentEmail );
                            update_user_meta($user_id,'first_name',$listitem[0]->agentName);

                        } else {
                            $random_password = __('User already exists.  Password inherited.');
                        }

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
                            '_wre_listing_zip' => $listitem[0]->zip,
                            '_wre_listing_agent' => $user_id,
                            '_wre_listing_building_size' => $listitem[0]->area

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


                        $insups = 'inserted';
                        if($listitem[1]){
                            $wp_appfo_post['ID'] = $listitem[1]->ID;// var_dump($wp_appfo_post);
                            $listitem[0]->ID =  wp_update_post( $wp_appfo_post );
                            $insups = 'updated';
                            $listitem[0]->createGallery();
                        }
                        else{
                            $listitem[0]->ID =  wp_insert_post( $wp_appfo_post );
                            $listitem[0]->createGallery();
                        }

                        wp_set_post_terms( $listitem[0]->ID,$tags,'listing-type');





                        $_appfosyncLogger->info('Listing has been '.$insups.' with the post id '.$listitem[0]->ID  .' and appfolio ref :'.$listid  );

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
            //throw new \Exception('Developer Testing');

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

            echo json_encode(array(
                'Message' => 'Error on the process "doappfosync" '.$e->getMessage(),
            ));
            exit(0);
        }
    }

    public function doappfosyncsingle(){
        try{
                if(isset($_GET['lid'])) {
                    $id = $_GET['lid'];

                    $item = $this->wrapper->listingDetail($id);

                    echo json_encode(array(
                        'ListingItem' => $item
                    ));

                }
                else{
                    echo json_encode(array(
                        'Message' => 'Invalid id'
                    ));

                }
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
            if($this->images) {
                $_wre_listing_image_gallery = array();
                foreach ($this->images as $image) {


                    $response = wp_remote_get($image);

                    if (!is_wp_error($response)) {
                        $bits = wp_remote_retrieve_body($response);

                        $name_arr = explode("/", $image);
                        $filename = $name_arr[5] . $name_arr[6];
                        // $filename = strtotime("now").'_'.uniqid().'.jpg';

                        $upload = wp_upload_bits($filename, null, $bits);
                        $data['guid'] = $upload['url'];
                        $data['post_mime_type'] = 'image/jpeg';
                        $attach_id = wp_insert_attachment($data, $upload['file'], 0);
                        $_wre_listing_image_gallery[$attach_id] =  $upload['url'];
                    }
                }
                add_post_meta($this->ID, '_wre_listing_image_gallery', $_wre_listing_image_gallery, true);
            }
        }
        catch(\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }
    }

    public function updateAttachments(){
        try{
                foreach($this->images as $image){

                }
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
        $this->limited = get_option( APPFOSYPERFIX . 'limit' );

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
                    if($this->limited > 0 && $_limit >= $this->limited) break;
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
        try {
            $listing_posttype = get_option(APPFOSYPERFIX . 'listing_posttype');
            $listing_query = new \WP_Query(array('post_type' => $listing_posttype, 'meta_query' => array(array('key' => '_wre_listing_mls', 'value' => $listingid))));
            if ($listing_query->have_posts()) {
                global $post;
                $listing_query->the_post();
                $_post = $post;
                wp_reset_postdata();
                return $_post;
            } else {
                return false;
            }

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
            $list = $html->find('h3.listing-detail__list-header ~ ul.list');
            foreach ($list[0]->children as $it){
                $_start = strpos($it->innertext,'$');
                $_length = strlen($it->innertext);
                if(strpos($it->innertext,"Rent: $")!== false){ $listItem->amount =  substr($it->innertext,$_start+1,$_length - $_start) ; }
                if(strpos($it->innertext,"Application Fee: $")!== false){ $listItem->applicationFee =  substr($it->innertext,$_start+1,$_length - $_start) ; }
                if(strpos($it->innertext,"Security Deposit: $")!== false){ $listItem->secDeposit =  substr($it->innertext,$_start+1,$_length - $_start) ; }
            }
            if(!$listItem->amount){
                foreach ($list[1]->children as $it){
                    $_start = strpos($it->innertext,'$');
                    $_length = strlen($it->innertext);
                    if(strpos($it->innertext,"Rent: $")!== false){ $listItem->amount =  substr($it->innertext,$_start+1,$_length - $_start) ; }
                    if(strpos($it->innertext,"Application Fee: $")!== false){ $listItem->applicationFee =  substr($it->innertext,$_start+1,$_length - $_start) ; }
                    if(strpos($it->innertext,"Security Deposit: $")!== false){ $listItem->secDeposit =  substr($it->innertext,$_start+1,$_length - $_start) ; }
                }



                $listItem->description .= '<h3>Amenities</h3><ul class="amanties">'.$list[0]->innertext .'</ul><h3>Rental Terms</h3><ul class="pet-policy">'. $list[1]->innertext.'</ul><h3>Pet Policy</h3><ul class="pet-policy">'. $list[2]->innertext.'</ul>';
            }else{
                $listItem->description .= '<h3>Rental Terms</h3><ul class="pet-policy">'. $list[0]->innertext.'</ul><h3>Pet Policy</h3><ul class="pet-policy">'. $list[1]->innertext.'</ul>';
            }

            $listItem->amount = str_replace(",","",$listItem->amount);
            $listItem->applicationFee = str_replace(",","" ,$listItem->applicationFee);
            $listItem->secDeposit = str_replace(",","",$listItem->secDeposit);

            $listItem->description .=  '<a href="https://smartland.appfolio.com/listings/rental_applications/new?listable_uid='.$listItem->listingId.'&source=Website" class="btn btn-primary button">Apply Now</a>';


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
            $lowres = $listing_posttype = get_option(APPFOSYPERFIX . 'lowres');
            if($lowres) {
                $_images = $html->find('img[src$="/medium.jpg"].gallery__small-image'); //a[href$="large.jpg"].swipebox
                foreach($_images as $image){
                    $listItem->images[] = $image->src;
                }
            }else {
                $_images = $html->find('a[href$="large.jpg"].swipebox');
                foreach ($_images as $image) {
                    $listItem->images[] = $image->href;
                }
            }

            $html->clear();
            unset($html);

            return $listItem;

        }
        catch (\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }

    }



}