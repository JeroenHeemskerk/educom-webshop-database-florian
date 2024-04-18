<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {session_start();}
$page = getRequestedPage();
$data = processRequest($page);
showPage($data);

function getRequestedPage() {
    $request_type = $_SERVER['REQUEST_METHOD'];
    if ($request_type == "POST") {
        $request_page = getPostVar("page");
    }
    else {
        $request_page = getGetVar("page");
    }
    return $request_page;
}

function getGetVar($key, $default="") {  
    $value = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);  
     
    return isset($value) ? trim($value) : $default;
}

function getPostVar($key, $default="", $filter=false) { 
    $value = filter_input(INPUT_POST, $key, $filter | FILTER_SANITIZE_SPECIAL_CHARS); 

    return isset($value) ? trim($value) : $default;   
}

function processRequest($page) {
    $data = processPage($page);

    // Build the dynamic navigation bar
    $data["menu"] = buildMenu();
    return $data;

}

function processPage($page) {
    // returns errors and asked values + applies rerouting
    switch ($page) {
        case "contact":
            include_once('contact.php');
            $data = validateContact();
            if ($data["valid"]) {
                $data["page"] = "thanks";
            }
            else {
                $data["page"] = $page;
            }
            return $data;

        case "login":
            include_once('login.php');
            $data = validateLogin();
            if ($data["valid"]) {
                include_once('session_manager.php');
                doLoginUser($data["values"]);
                $data["page"] = "home";
            }
            else {
                $data["page"] = $page;
            }
            return $data;

        case "logout":
            include_once('session_manager.php');
            doLogoutUser();
            return ["page"=>"home"];

        case "register":
            include_once('register.php');
            $data = validateRegister();
            if ($data["valid"]) {
                try {
                    addAccount($data["values"]);
                    $data["page"] = "home";
                }
                catch (Exception $e) {
                    $data["errors"] = array();
                    $data["errors"]["general"] = "Er is een technische storing, u kunt niet registreren. Probeer het later nogmaals.";
                    logError('Registration failed for user ' . $data["values"]['email'] . ', SQLError: ' . $e -> getMessage());
                    $data["page"] = $page;
                }                
            }
            else {
                $data["page"] = $page;
            }
            return $data;

        case "shop":
            // voor iedere webshop pagina vraag ik nu alle producten op
            // daar ben ik nog niet heel tevreden over
            include_once('communication.php');
            try {
                $data = getProducts();
            }
            catch (Exception $e) {
                $errors["general"] = "Er is een technische storing, de website kan niet worden geladen. Probeer het later nogmaals.";
                logError('Shop load failed SQLError: ' . $e -> getMessage());
                $data = ["errors" => $errors];
            }

            $data["productId"] = getGetVar("detail", 0);
            $data["page"] = $page;
            return $data;

        case "cart":
            $action = getPostVar('action');
            $id = getPostVar('productId');
            include_once('cart.php');
            if (empty($action)) {
                try {
                    $data = getCartProducts();
                    $data["page"] = $page;
                }

                catch (Exception $e) {
                    $data["errors"] = array();
                    $data["errors"]["general"] = "Er is een technische storing, u kunt uw winkelmand niet inzien. Probeer het later nogmaals.";
                    logError('Failed cart load SQLError: ' . $e -> getMessage());
                    $data["page"] = "home";
                }
                return $data;
            }

            return handleCartAction($action, $id);

        default:
            return ["page"=>$page];
    }
}

function buildMenu() {
    $menu = array("home"=>"HOME", "about"=>"ABOUT", "contact"=>"CONTACT", "shop"=>"WEBSHOP");
    include_once('session_manager.php');
    if (isUserLoggedIn()) {
        $menu["cart"] = 'CART';
        $menu["logout"] = 'LOGOUT ' . getLoggedInUserName();
    } 
    else {
        $menu["register"] = "REGISTER";
        $menu["login"] = "LOGIN";
    }
    return $menu;
}

function beginDocument() {
    echo '<!doctype html> 
    <html>'; 
}

function showHeader($data) {
    echo "<head><title>";
    echo showTitle($data["page"]);
    echo "</title>";    
    echo '<link rel="icon" type="svg" href="Images/online-form-icon.svg">';
    echo '<link rel="stylesheet" type="text/css" href="CSS/styles.css">';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Merriweather|Open+Sans">';
    echo '</head>';
}

function showTitle($page) {
    switch ($page) {
        case "contact":
        case "thanks":
            include_once('contact.php');
            return getContactTitle();
        case "about":
            include_once('about.php');
            return getAboutTitle();
        case "home":
            include_once('home.php');
            return getHomeTitle();
        case "register":
            include_once('register.php');
            return getRegisterTitle();
        case "login":
            include_once('login.php');
            return getLoginTitle();
        case "shop":
            include_once('shop.php');
            return getShopTitle();
        case "cart":
            include_once('cart.php');
            return getCartTitle();

        default:
            include_once('error404.php');
            return get404Title();

    }
}

function showGeneralError($data) {
    if (!empty($data['errors']['general'])) {
        echo '<div class="error">' . $data['errors']['general'] . '</div>';
    }
}

function logError($msg) {
    echo "LOGGING TO THE SERVER: " . $msg;
 }

function showBody($data) {
    echo "<body>" . PHP_EOL;
    echo "<h1>Florian&apos;s Rariteitenkabinet</h1>";
    showNavBar($data);
    showGeneralError($data);
    showContent($data);
    showFooter();
    echo "</body>" . PHP_EOL;
}

function showNavBar($data) {
    $menu = $data["menu"];
    echo '<ul class="navbar">';
    foreach($menu as $page=>$label) {
        echo '<li><a href="index.php?page=' . $page . '">' . $label . '</a></li>';
    }
    echo '</ul>';
}

function showContent($data) {
    $page = $data["page"];

    switch ($page) {
        case "about":
            include_once('about.php');
            showAboutContent();
            break;

        case "contact":
            include_once('contact.php');
            showContactContent($data);
            break;
        
        case "thanks":
            include_once('contact.php');
            showContactThanks($data);
            break;

        case "home":
            include_once('home.php');
            showHomeContent();
            break;

        case "register":
            include_once('register.php');
            showRegisterContent($data);
            break;

        case "login":
            include_once('login.php');
            showLoginContent($data);
            break;

        case "shop":
            include_once('shop.php');
            showShopContent($data);
            break;

        case "cart":
            include_once('cart.php');
            showCartContent($data);
            break;

        default:
            include_once('error404.php');
            show404Content();
    }
}

function showPage($page) {
    beginDocument();
    showHeader($page);
    showBody($page);
    echo "</html>";
}

function showFooter() {
    echo '<footer>
    <p>&copy; Florian van der Steen 2024<br></p>
    </footer>';
}
