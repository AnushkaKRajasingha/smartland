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
                $agentasuser = get_option( APPFOSYPERFIX . 'agentasuser' );//agentasuser
                $agentasusershow = get_option( APPFOSYPERFIX . 'agentasusershow' );//agentasusershow
                $_admin_user_id = $this->admin_user_ids();

                $counter = 0 ;
                foreach ($this->appfoliolistings as $listid => $listitem) {
                    //$listitem[0] = new clsAppfolioListItem();

                    $_appfosyncLogger = new clsAppfoSyLogWriter();

                    try{
                        if($agentasuser) {
                             $username = substr($listitem[0]->agentEmail,0,strpos($listitem[0]->agentEmail,'@'));
                             $user_id = username_exists( $username );
                             if ( !$user_id and email_exists($listitem[0]->agentEmail) == false ) {
                                 $random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
                                 $user_id = wp_create_user( $username, $random_password, $listitem[0]->agentEmail );
                                 update_user_meta($user_id,'first_name',$listitem[0]->agentName);
                                 update_user_meta($user_id,'role','wre_agent');
                                 update_user_meta($user_id,'phone',$listitem[0]->agentPhone);
                                 //update_user_meta($user_id,'mobile',$listitem[0]->agentPhone);
                                 $user = new \WP_User($user_id);
                                 $user->add_role('wre_agent');
                                 $_admin_user_id[0] = $user_id;

                             } else {
                                 $random_password = __('User already exists.  Password inherited.');
                             }
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
                            '_wre_listing_agent' => $_admin_user_id[0],
                            '_wre_listing_hide' => ($agentasusershow == true) ? array() : array('4' => 'contact_form','5' => 'agent'),
                            '_wre_listing_building_size' => $listitem[0]->area,
                            '_wre_listing_image_gallery' => array()

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
                        $_appfosyncLogger->emailNotification('Updating record in wordpress with '.$listid.' was unsuccessfull.');
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

    function admin_user_ids(){
        //Grab wp DB
        global $wpdb;
        //Get all users in the DB
        $wp_user_search = $wpdb->get_results("SELECT ID, display_name FROM $wpdb->users ORDER BY ID");

        //Blank array
        $adminArray = array();
        //Loop through all users
        foreach ( $wp_user_search as $userid ) {
            //Current user ID we are looping through
            $curID = $userid->ID;
            //Grab the user info of current ID
            $curuser = get_userdata($curID);
            //Current user level
            $user_level = $curuser->user_level;
            //Only look for admins
            if($user_level >= 8){//levels 8, 9 and 10 are admin
                //Push user ID into array
                $adminArray[] = $curID;
            }
        }
        return $adminArray;
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

    function attachmentExists($guid){
        try{
            global $wpdb;
            $attachment = $wpdb->get_row($wpdb->prepare("SELECT ID,guid FROM $wpdb->posts WHERE post_parent = %d and guid = %s ;",array($this->ID , $guid) ));
           //echo $wpdb->last_query.'<br/>';// var_dump($attachment);
            if(count($attachment) <= 0) return false;
            return $attachment;
        }
        catch(\Exception $e){
            $_appfosyncLogger = new clsAppfoSyLogWriter();
            $_appfosyncLogger->warning($e->getMessage());
        }
    }

    function createGallery(){
        $_appfosyncLogger = new clsAppfoSyLogWriter();
        try{
            if($this->images) {
                $_wre_listing_image_gallery = array();
                foreach ($this->images as $image) {
                    $name_arr = explode("/", $image);
                    $filename = $name_arr[5] .   $name_arr[6];

                    $upload_dir   = wp_upload_dir();


                    $attachment_count = count($_wre_listing_image_gallery)+1;
                    $file_shortname = 'Listing-'.$this->ID.'-image-'.$attachment_count;

                    $attachment = $this->attachmentExists($upload_dir['url'].'/'.$filename);
                    if($attachment) {
                        if(!empty($attachment->guid) && $attachment->guid != null) {
                            //echo $attachment->guid .'<br/>';
                            $_wre_listing_image_gallery[$attachment->ID] = $attachment->guid;
                            $_appfosyncLogger->info('Updated media file  - ' . $filename);
                        }
                    }
                    else {
                        $response = wp_remote_get($image);
                        if (!is_wp_error($response)) {
                            $bits = wp_remote_retrieve_body($response);


                            // $filename = strtotime("now").'_'.uniqid().'.jpg';

                            $upload = wp_upload_bits($filename, null, $bits);
                            $data['guid'] = $upload['url'];
                            $data['post_mime_type'] = 'image/jpeg';
                            $data['post_title'] = $file_shortname;

                            require_once(ABSPATH . 'wp-admin/includes/image.php');

                            $attach_id = wp_insert_attachment($data, $upload['file'], $this->ID);

                            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
                            wp_update_attachment_metadata($attach_id, $attach_data);

                            //if ($attachment_count == 1) set_post_thumbnail($this->ID, $attach_id);

                            $_appfosyncLogger->info('New media file added - '. $filename );
                        }
                    }

                    $_wre_listing_image_gallery[$attach_id] =  $upload['url'];

                }
                $_wre_listing_image_gallery = array_filter($_wre_listing_image_gallery,array($this,'sanitizearray'),ARRAY_FILTER_USE_BOTH);
/*                echo '<pre>';
                var_dump($_wre_listing_image_gallery);
                echo '</pre>';*/
                delete_post_meta($this->ID, '_wre_listing_image_gallery');
                add_post_meta($this->ID, '_wre_listing_image_gallery', $_wre_listing_image_gallery);
            }
        }
        catch(\Exception $e){

            $_appfosyncLogger->warning($e->getMessage());
        }
    }

    function sanitizearray($key,$value){
        return !empty($key) && preg_match('/[^?]*\.(jpg|jpeg|gif|png)/',$key);
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

            $listingsList_array = array();
            $counter = 0; $_limit = 1;

            $listingUrl = get_option( APPFOSYPERFIX . 'listing_url' );

            //Validating listing url
            if($listingUrl === '#' || !preg_match("/(http[s]?:\/\/)?[^\s([\"<,>]*\.[^\s[\",><]*/",$listingUrl)) { throw new \Exception( 'invalid URL - '.$listingUrl ); exit();}

            $result = parse_url($listingUrl);
            $domain = $result['scheme'] . "://" . $result['host'];

            if((isset($_POST['lid']) && !empty($_POST['lid'])) ||  (isset($_GET['lid']) && !empty($_GET['lid']))){


                $_listingid = (isset($_POST['lid']) && !empty($_POST['lid'])) ? $_POST['lid'] : $_GET['lid'];
                $_post = $this->getPostbyListingid($_listingid);

                $listingsList_array[$_listingid] = [$domain,$this->needle.$_listingid,1,$_post];

                return$listingsList_array;
            }


            require_once 'shmAppfosync.php';

            $html   = file_get_html($listingUrl);

            $listingsList = $html->find('a.btn.js-link-to-detail[href^="'.$this->needle.'"]');


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
            $_appfosyncLogger->emailNotification('Scraping the listing from '.$listingUrl.' was unsuccessfull.');
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

            // Agent details


            $agent = substr($listItem->description,strpos($listItem->description,"Leasing Agent")+15);
            $agentEndpos = strpos($agent,"<br />")-4;
            $agent = substr($agent,0,$agentEndpos);
            // $listItem->agentName = substr($agent,0,strpos($agent," -"));
            // $listItem->agentPhone = substr($agent,strpos($agent,"- ")+2);$listItem->agentPhone = substr($listItem->agentPhone,0,strpos($listItem->agentPhone," |"));
            //$listItem->agentEmail = trim(substr($agent,strpos($agent,"| ")+2));
            // $listItem->agentPhone = $agent;
            $matches = array();
            if(preg_match("/(Agent\W+\w+\s\w+)/", $listItem->description,$matches,PREG_OFFSET_CAPTURE,0)){
                $listItem->agentName = $matches[0][0];
            }
            if(preg_match("/(?:\(|\b)[\d]{3}\s*\)?[.-]?\s*[\d]{3}\s*[.-]\s*[\d]{3,4}\b/", $listItem->description,$matches,PREG_OFFSET_CAPTURE,0)){
                $listItem->agentPhone = $matches[0][0];
            }
            if(preg_match("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+\.([a-z]{2,4})/i", $listItem->description,$matches,PREG_OFFSET_CAPTURE,0)){
                $listItem->agentEmail = $matches[0][0];
            }

            $listItem->description .= '<h3>Agent Details</h3><ul class="agent"><li>'.$listItem->agentName.'</li><li>Phone - <a href="tel:'.$listItem->agentPhone.'">'.  $listItem->agentPhone .'</a></li><li>Email - <a href="mailto:'.$listItem->agentEmail.'">'.$listItem->agentEmail.'</a></li></ul>';


            $listItem->amount = str_replace(",","",$listItem->amount);
            $listItem->applicationFee = str_replace(",","" ,$listItem->applicationFee);
            $listItem->secDeposit = str_replace(",","",$listItem->secDeposit);

            $listItem->description .=  '<a href="https://smartland.appfolio.com/listings/rental_applications/new?listable_uid='.$listItem->listingId.'&source=Website" class="btn btn-primary button" target="_blank">Apply Now</a>';


            // pet policy
            $list = $html->find('div.grid > div.grid__large-6.grid__medium-6.grid__small-12 li.list__item.js-pet-policy-item');
            $listItem->petAllowed = array();
            foreach ($list as $item){
                array_push($listItem->petAllowed,$item->innertext);
            }

            // applynow url
            $listItem->applyNowUrl = "https://smartland.appfolio.com/listings/rental_applications/new?listable_uid=".$listItem->listingId;



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
            $_appfosyncLogger->emailNotification('Scraping the listing details from '.$listingId.' was unsuccessfull.');
        }

    }



}