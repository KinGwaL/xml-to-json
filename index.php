<?php
/**
 * XML to JSON
 *
 * @author Ethan Jinks O'Sullivan
 * @link https://www.factmaven.com/api/json
 * @version 1.1.0
 */

// Lets the browser and tools such as Postman know it's JSON
// header( "Content-Type: application/json" );

// Get XML source through the 'xml' parameter
if ( isset( $_GET['url'] ) ) {
    $xml = simplexml_load_file( $_GET['url'] );
    $json = xmlToArray( $xml );
} else {
    $json = [
        "errors" => [
            "id" => "404",
            "title" => "Missing Parameter",
            "detail" => "Please set the path to your XML by using the '?url=' query string.",
        ],
        "meta" => [
        "version" => "1.1.0",
        "copyright" => "Copyright 2011-" . date("Y") . " Fact Maven Corp.",
        "link" => "https://www.factmaven.com/",
        "authors" => [
                "Ethan Jinks O'Sullivan",
            ],
        ],
    ];
}

function xmlToArray( $xml, $options = array() ) {
    $defaults = array( 
        'namespaceSeparator' => ':', // you may want this to be something other than a colon
        'attributePrefix' => '@', // to distinguish between attributes and nodes with the same name
        'alwaysArray' => array(), // array of XML tag names which should always become arrays
        'autoArray' => TRUE, // only create arrays for tags which appear more than once
        'textContent' => '#text', // key used for the text content of elements
        'autoText' => TRUE, // skip textContent key if node has no attributes or child nodes
        'keySearch' => FALSE, // optional search and replace on tag and attribute names
        'keyReplace' => FALSE, // replace values for above search values ( as passed to str_replace() )
    );
    $options = array_merge( $defaults, $options );
    $namespaces = $xml->getDocNamespaces();
    $namespaces[''] = NULL; // add base ( empty ) namespace
    // Get attributes from all namespaces
    $attributesArray = array();
    foreach ( $namespaces as $prefix => $namespace ) {
        foreach ( $xml->attributes( $namespace ) as $attributeName => $attribute ) {
            // Replace characters in attribute name
            if ( $options['keySearch'] ) {
                $attributeName = str_replace( $options['keySearch'], $options['keyReplace'], $attributeName );
            }
            $attributeKey = $options['attributePrefix'] . ( $prefix ? $prefix . $options['namespaceSeparator'] : '' ) . $attributeName;
            $attributesArray[$attributeKey] = ( string )$attribute;
        }
    }
    // Get child nodes from all namespaces
    $tagsArray = array();
    foreach ( $namespaces as $prefix => $namespace ) {
        foreach ( $xml->children( $namespace ) as $childXml ) {
            // Recurse into child nodes
            $childArray = xmlToArray( $childXml, $options );
            list( $childTagName, $childProperties ) = each( $childArray );
            // Replace characters in tag name
            if ( $options['keySearch'] ) {
                $childTagName = str_replace( $options['keySearch'], $options['keyReplace'], $childTagName );
            }
            // Add namespace prefix, if any
            if ( $prefix ) {
                $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;
            }

            if ( !isset( $tagsArray[$childTagName] ) ) {
                // Only entry with this key. Test if tags of this type should always be arrays, no matter the element count
                $tagsArray[$childTagName] =
                        in_array( $childTagName, $options['alwaysArray'] ) || !$options['autoArray']
                        ? array( $childProperties ) : $childProperties;
            } elseif ( 
                is_array( $tagsArray[$childTagName] ) && array_keys( $tagsArray[$childTagName] )
                === range( 0, count( $tagsArray[$childTagName] ) - 1 )
            ) {
                // Key already exists and is integer indexed array
                $tagsArray[$childTagName][] = $childProperties;
            } else {
                // Key exists so convert to integer indexed array with previous value in position 0
                $tagsArray[$childTagName] = array( $tagsArray[$childTagName], $childProperties );
            }
        }
    }
    // Get text content of node
    $textContentArray = array();
    $plainText = trim( ( string )$xml );
    if ( $plainText !== '' ) $textContentArray[$options['textContent']] = $plainText;
    // Stick it all together
    $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ( $plainText === '' )
            ? array_merge( $attributesArray, $tagsArray, $textContentArray ) : $plainText;
    // Return node as array
    return array( $xml->getName() => $propertiesArray );
}
// Output JSON
// echo json_encode( $json );

// DEBUG: Array output
print_r( $json );