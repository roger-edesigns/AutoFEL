<?php

namespace Certificadores;

require_once plugin_dir_path(__FILE__) . 'certificador.class.php';
use Certificador;

class DigiFact implements Certificador {

    public $data = [];
    private $id = "";

    public function __construct($id) {
        $this->id = $id;
    }

    public function send() {

        $order = wc_get_order($this->id);

        $now = new \DateTime();
        $now->setTimezone(new \DateTimeZone('America/Guatemala'));
        $now = $now->format('Y-m-d\TH:i:s');
        
        $data["id"] = $order->get_id();
        $data["cliente"] = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
        $data["total"] = $order->get_total();
        $data["items"] = $order->get_items();
        
        $data["nit"] = $order->get_meta("_billing_nit");

        if(!$data["nit"] || $data["nit"] == "CF" || $data["nit"] == "C/F" || $data["nit"] == "c/f" || $data["nit"] == "cf" || $data["nit"] == "c f" || $data["nit"] == "C F") {
            $data["nit"] = "CF";
        }

        $username = get_option('auto-fel-settings-certificador-user');
        $password = get_option('auto-fel-settings-certificador-password');
        $nit = get_option('auto-fel-settings-nit');
        $nombre_emisor = get_option('auto-fel-settings-nombre-emisor');
        $nombre_comercial = get_option('auto-fel-settings-nombre-comercial');
        $regimen = get_option('auto-fel-settings-regimen');
        $direccion = get_option('auto-fel-settings-direccion');
        $codigo_postal = get_option('auto-fel-settings-codigo-postal');
        $municipio = get_option('auto-fel-settings-municipio');
        $departamento = get_option('auto-fel-settings-departamento');
        $codigo_pais = get_option('auto-fel-settings-codigo-pais');
        
        $cliente_address = $order->get_billing_address_1() . " " . $order->get_billing_address_2();
        $cliente_city = $order->get_billing_city();
        $cliente_state = WC()->countries->states[$order->get_billing_country()][$order->get_billing_state()];
        $cliente_zip = $order->get_billing_postcode() || "00000";
        $cliente_country = $order->get_billing_country();
        
        $test_mode = get_option('auto-fel-settings-testmode');
        $debug = get_option('auto-fel-settings-debug');
        
        if($regimen == 'general') {
            $regimen = "GEN";
            $tipo_documento = "FACT";
        }
        else if($regimen == 'fpq') {
            $regimen = "PEQ";
            $tipo_documento = "FPEQ";
        }

        // send authorization
        if($test_mode) {
            $url_api = "https://felgttestaws.digifact.com.gt";
        }
        else {
            $url_api = "https://felgtaws.digifact.com.gt";
        }

        $auth_data = array(
            "username" => "{$codigo_pais}.000{$nit}.{$username}",
            "password" => $password
        );

        $data_string = json_encode($auth_data);

        $ch = curl_init($url_api . "/gt.com.fel.api.v3/api/login/get_token");

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)
        ));

        $result = curl_exec($ch);

        $result = json_decode($result);

        $token = $result->Token;

        // send invoice
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><dte:GTDocumento xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:dte="http://www.sat.gob.gt/dte/fel/0.2.0"/>');
        
        $xml->addAttribute('Version', '0.1');
        $xml->addChild('dte:SAT');
        $xml->children('dte', true)->SAT->addAttribute('ClaseDocumento', 'dte');
        $xml->children('dte', true)->SAT->addChild('dte:DTE');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->addAttribute('ID', 'DatosCertificados');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->addChild('dte:DatosEmision');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->addAttribute('ID', 'DatosEmision');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->addChild('dte:DatosGenerales');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->DatosGenerales->addAttribute('Tipo', $tipo_documento);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->DatosGenerales->addAttribute('FechaHoraEmision', $now);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->DatosGenerales->addAttribute('CodigoMoneda', $order->get_currency());
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->addChild('dte:Emisor');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->addAttribute('NITEmisor', $nit);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->addAttribute('NombreEmisor', $nombre_emisor);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->addAttribute('CodigoEstablecimiento', '1');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->addAttribute('NombreComercial', $nombre_comercial);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->addAttribute('AfiliacionIVA', $regimen);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->addChild('dte:DireccionEmisor');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->children('dte', true)->DireccionEmisor->addChild('dte:Direccion', $direccion);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->children('dte', true)->DireccionEmisor->addChild('dte:CodigoPostal', $codigo_postal);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->children('dte', true)->DireccionEmisor->addChild('dte:Municipio', $municipio);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->children('dte', true)->DireccionEmisor->addChild('dte:Departamento', $departamento);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Emisor->children('dte', true)->DireccionEmisor->addChild('dte:Pais', $codigo_pais);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->addChild('dte:Receptor');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Receptor->addAttribute('NombreReceptor', $data['cliente']);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Receptor->addAttribute('IDReceptor', $data['nit']);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Receptor->addChild('dte:DireccionReceptor');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Receptor->children('dte', true)->DireccionReceptor->addChild('dte:Direccion', $cliente_address);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Receptor->children('dte', true)->DireccionReceptor->addChild('dte:CodigoPostal', $cliente_zip);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Receptor->children('dte', true)->DireccionReceptor->addChild('dte:Municipio', $cliente_city);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Receptor->children('dte', true)->DireccionReceptor->addChild('dte:Departamento', $cliente_state);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Receptor->children('dte', true)->DireccionReceptor->addChild('dte:Pais', $cliente_country);
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->addChild('dte:Frases');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Frases->addChild('dte:Frase');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Frases->children('dte', true)->Frase->addAttribute('TipoFrase', '3');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Frases->children('dte', true)->Frase->addAttribute('CodigoEscenario', '1');
        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->addChild('dte:Items');

        $i = 0;
        foreach($data['items'] as $key => $item){
            $producto = wc_get_product($item->get_product_id());
            $precio_unitario = $producto->get_price();
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->addChild('dte:Item');
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addAttribute('NumeroLinea', $i + 1);
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addAttribute('BienOServicio', 'B');
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addChild('Cantidad', $item->get_quantity());
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addChild('UnidadMedida', 'CA');
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addChild('Descripcion', $item->get_name());
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addChild('PrecioUnitario', $precio_unitario);
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addChild('Precio', $item->get_subtotal());
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addChild('Descuento', '0');

            // Impuestos
            if($regimen === "general") {
                $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addChild('Impuestos');
                $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->children('dte', true)->Impuestos->addChild('Impuesto');
                $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->children('dte', true)->Impuestos->children('dte', true)->Impuesto->addChild('NombreCorto', 'IVA');
                $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->children('dte', true)->Impuestos->children('dte', true)->Impuesto->addChild('CodigoUnidadGravable', '1');
                $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->children('dte', true)->Impuestos->children('dte', true)->Impuesto->addChild('MontoGravable', $item->get_subtotal());
                $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->children('dte', true)->Impuestos->children('dte', true)->Impuesto->addChild('MontoImpuesto', $item->get_subtotal_tax());
            }

            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Items->children('dte', true)->Item[$i]->addChild('Total', $item->get_total());
            $i++;
        }

        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->addChild('dte:Totales');
        
        // Impuestos
        if($regimen === "general") {
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Totales->addChild('dte:TotalImpuestos');
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Totales->children('dte', true)->TotalImpuestos->addChild('dte:TotalImpuesto');
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Totales->children('dte', true)->TotalImpuestos->children('dte', true)->TotalImpuesto->addAttribute('NombreCorto', 'IVA');
            $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Totales->children('dte', true)->TotalImpuestos->children('dte', true)->TotalImpuesto->addAttribute('TotalMontoImpuesto', $order->get_total_tax());
        }

        $xml->children('dte', true)->SAT->children('dte', true)->DTE->children('dte', true)->DatosEmision->children('dte', true)->Totales->addChild('dte:GranTotal', $data['total']);

        $xml->children('dte', true)->SAT->addChild('dte:Adenda');
        $xml->children('dte', true)->SAT->children('dte', true)->Adenda->addChild('dtecomm:Informacion_COMERCIAL', '', 'https://www.digifact.com.gt/dtecomm');
        $xml->children('dte', true)->SAT->children('dte', true)->Adenda->children('dtecomm', true)->Informacion_COMERCIAL->addAttribute('xsi:schemaLocation', 'https://www.digifact.com.gt/dtecomm', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->children('dte', true)->SAT->children('dte', true)->Adenda->children('dtecomm', true)->Informacion_COMERCIAL->addChild('dtecomm:InformacionAdicional');
        $xml->children('dte', true)->SAT->children('dte', true)->Adenda->children('dtecomm', true)->Informacion_COMERCIAL->children('dtecomm', true)->InformacionAdicional->addAttribute('dtecomm:Version', '7.1234654163');
        $xml->children('dte', true)->SAT->children('dte', true)->Adenda->children('dtecomm', true)->Informacion_COMERCIAL->children('dtecomm', true)->InformacionAdicional->addChild('dtecomm:REFERENCIA_INTERNA', $data['id'] + 5);
        $xml->children('dte', true)->SAT->children('dte', true)->Adenda->children('dtecomm', true)->Informacion_COMERCIAL->children('dtecomm', true)->InformacionAdicional->addChild('dtecomm:FECHA_REFERENCIA', $order->get_date_created()->date('Y-m-d\TH:i:s'));
        $xml->children('dte', true)->SAT->children('dte', true)->Adenda->children('dtecomm', true)->Informacion_COMERCIAL->children('dtecomm', true)->InformacionAdicional->addChild('dtecomm:VALIDAR_REFERENCIA_INTERNA', 'VALIDAR');

        $r = $xml->asXML();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_api . "/gt.com.fel.api.v3/api/FelRequestV2?NIT={$nit}&TIPO=CERTIFICATE_DTE_XML_TOSIGN&FORMAT=XML,PDF&USERNAME={$username}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $r);

        $headers = array();
        $headers[] = 'Content-Type: application/xml';
        $headers[] = "Authorization: {$token}";

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);

        curl_close($ch);

        $json = json_decode($result, true);


        add_action( 'phpmailer_init', function(&$phpmailer) use ($json) {
            $phpmailer->SMTPKeepAlive = true;
            $phpmailer->AddStringAttachment(base64_decode($json["ResponseDATA3"]), "DTE.pdf", 'base64', 'application/pdf');
        });

        // PDF
        if(isset($json['ResponseDATA3'])) {

            $email_nit = $data["nit"];
            $email_order_id = $data["id"];
            $email_total = $data["total"];
            $email_nombre_comercial = $nombre_comercial;

            $html = <<<HTML
                <p>
                    ¡Gracias por tu compra en JS.gt!<br>
                    Adjunto encontrará documento tributario electrónico (DTE) correspondiente a su reciente compra.
                </p>
                <p>
                    - Nit: {$email_nit}<br>
                    - Orden: #{$email_order_id}<br>
                    - Monto: Q{$email_total}
                </p>
                <p>
                    Atentamente:<br>
                    Equipo de {$email_nombre_comercial}
                </p>
            HTML;

            wp_mail($order->get_billing_email(), "DTE: {$email_nombre_comercial} - {$nombre_comercial}", $html, array('Content-Type: text/html; charset=UTF-8'));
        }

        if($debug) {
            $dom = new \DOMDocument();
            $dom->loadXML($r);
            $dom->formatOutput = true;
            
            echo '<pre>';
                print_r(htmlentities($dom->saveXML()));
            echo '</pre>';

            print_r($json);
            exit;
        }

    }

    public function cancel() {
        
    }

}

?>