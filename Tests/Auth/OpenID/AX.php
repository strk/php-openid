<?php

/*
 * Tests for the attribute exchange extension module
 */

require_once "PHPUnit.php";
require_once "Auth/OpenID/AX.php";
require_once "Auth/OpenID/Message.php";

class BogusAXMessage extends Auth_OpenID_AX_Message {
    var $mode = 'bogus';

    function getExtensionArgs()
    {
        return $this->_newArgs();
    }
}

class AXMessageTest extends PHPUnit_TestCase {
    function setUp()
    {
        $this->bax = new BogusAXMessage();
    }

    function test_checkMode()
    {
        $result = $this->bax->_checkMode(array());
        $this->assertTrue(Auth_OpenID_AX::isError($result));

        $result = $this->bax->_checkMode(array('mode' => 'fetch_request'));
        $this->assertTrue(Auth_OpenID_AX::isError($result));

        // does not raise an exception when the mode is right
        $result = $this->bax->_checkMode(array('mode' => $this->bax->mode));
        $this->assertTrue($result === true);
    }

    /*
     * _newArgs generates something that has the correct mode
     */
    function test_checkMode_newArgs()
    {
        $result = $this->bax->_checkMode($this->bax->_newArgs());
        $this->assertTrue($result === true);
    }
}

class AttrInfoTest extends PHPUnit_TestCase {
    function test_construct()
    {
        $type_uri = 'a uri';
        $ainfo = new Auth_OpenID_AX_AttrInfo($type_uri);

        $this->assertEquals($type_uri, $ainfo->type_uri);
        $this->assertEquals(1, $ainfo->count);
        $this->assertFalse($ainfo->required);
        $this->assertTrue($ainfo->alias === null);
    }
}

class ToTypeURIsTest extends PHPUnit_TestCase {
    function setUp()
    {
        $this->aliases = new Auth_OpenID_NamespaceMap();
    }

    function test_empty()
    {
        foreach (array(null, '') as $empty) {
            $uris = Auth_OpenID_AX_toTypeURIs($this->aliases, $empty);
            $this->assertEquals(array(), $uris);
        }
    }

    function test_undefined()
    {
        $result = Auth_OpenID_AX_toTypeURIs($this->aliases,
                                            'http://janrain.com/');
        $this->assertTrue(Auth_OpenID_AX::isError($result));
    }

    function test_one()
    {
        $uri = 'http://janrain.com/';
        $alias = 'openid_hackers';
        $this->aliases->addAlias($uri, $alias);
        $uris = Auth_OpenID_AX_toTypeURIs($this->aliases, $alias);
        $this->assertEquals(array($uri), $uris);
    }

    function test_two()
    {
        $uri1 = 'http://janrain.com/';
        $alias1 = 'openid_hackers';
        $this->aliases->addAlias($uri1, $alias1);

        $uri2 = 'http://jyte.com/';
        $alias2 = 'openid_hack';
        $this->aliases->addAlias($uri2, $alias2);

        $uris = Auth_OpenID_AX_toTypeURIs($this->aliases,
                                          implode(',', array($alias1, $alias2)));
        $this->assertEquals(array($uri1, $uri2), $uris);
    }
}

class ParseAXValuesTest extends PHPUnit_TestCase {
    function failUnlessAXKeyError($ax_args)
    {
        $msg = new Auth_OpenID_AX_KeyValueMessage();
        $result = $msg->parseExtensionArgs($ax_args);
        $this->assertTrue(Auth_OpenID_AX::isError($result));
        $this->assertTrue($result->message);
    }

    function failUnlessAXValues($ax_args, $expected_args)
    {
        $msg = new Auth_OpenID_AX_KeyValueMessage();
        $msg->parseExtensionArgs($ax_args);
        $this->assertEquals($expected_args, $msg->data);
    }

    function test_emptyIsValid()
    {
        $this->failUnlessAXValues(array(), array());
    }

    function test_missingValueForAliasExplodes()
    {
        $this->failUnlessAXKeyError(array('type.foo' => 'urn:foo'));
    }

    function test_countPresentButNotValue()
    {
        $this->failUnlessAXKeyError(array('type.foo' => 'urn:foo',
                                          'count.foo' => '1'));
    }

    function test_countPresentAndIsZero()
    {
        $this->failUnlessAXValues(
                                  array('type.foo' => 'urn:foo',
                                        'count.foo' => '0',
                                        ), array('urn:foo' => array()));
    }

    function test_singletonEmpty()
    {
        $this->failUnlessAXValues(
                                  array('type.foo' => 'urn:foo',
                                        'value.foo' => '',
                                        ), array('urn:foo' => array()));
    }

    function test_doubleAlias()
    {
        $this->failUnlessAXKeyError(
                                    array('type.foo' => 'urn:foo',
                                          'value.foo' => '',
                                          'type.bar' => 'urn:foo',
                                          'value.bar' => '',
                                          ));
    }

    function test_doubleSingleton()
    {
        $this->failUnlessAXValues(
                                  array('type.foo' => 'urn:foo',
                                        'value.foo' => '',
                                        'type.bar' => 'urn:bar',
                                        'value.bar' => '',
                                        ), array('urn:foo' => array(), 'urn:bar' => array()));
    }

    function test_singletonValue()
    {
        $this->failUnlessAXValues(
                                  array('type.foo' => 'urn:foo',
                                        'value.foo' => 'Westfall',
                                        ), array('urn:foo' => array('Westfall')));
    }
}

class FetchRequestTest extends PHPUnit_TestCase {
    function setUp()
    {
        $this->msg = new Auth_OpenID_AX_FetchRequest();
        $this->type_a = 'http://janrain.example.com/a';
        $this->alias_a = 'a';
    }

    function test_mode()
    {
        $this->assertEquals($this->msg->mode, 'fetch_request');
    }

    function test_construct()
    {
        $this->assertEquals(array(), $this->msg->requested_attributes);
        $this->assertEquals(null, $this->msg->update_url);

        $msg = new Auth_OpenID_AX_FetchRequest('hailstorm');
        $this->assertEquals(array(), $msg->requested_attributes);
        $this->assertEquals('hailstorm', $msg->update_url);
    }

    function test_add()
    {
        $uri = 'mud://puddle';

        // Not yet added:
        $this->assertFalse(in_array($uri, $this->msg->iterTypes()));

        $attr = new Auth_OpenID_AX_AttrInfo($uri);
        $this->msg->add($attr);

        // Present after adding
        $this->assertTrue(in_array($uri, $this->msg->iterTypes()));
    }

    function test_addTwice()
    {
        $uri = 'lightning://storm';

        $attr = new Auth_OpenID_AX_AttrInfo($uri);
        $this->msg->add($attr);
        $this->assertTrue(Auth_OpenID_AX::isError($this->msg->add($attr)));
    }

    function test_getExtensionArgs_empty()
    {
        $expected_args = array(
                               'mode' =>'fetch_request',
                               );
        $this->assertEquals($expected_args, $this->msg->getExtensionArgs());
    }

    function test_getExtensionArgs_noAlias()
    {
        $attr = new Auth_OpenID_AX_AttrInfo('type://of.transportation');

        $this->msg->add($attr);
        $ax_args = $this->msg->getExtensionArgs();
        $found = false;
        $alias = null;

        foreach ($ax_args as $k => $v) {
            if (($v == $attr->type_uri) && (strpos($k, 'type.') === 0)) {
                $alias = substr($k, 5);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->fail("Didn't find the type definition");
            return;
        }

        $this->failUnlessExtensionArgs(array(
            'type.' . $alias => $attr->type_uri,
            'if_available' => $alias));
    }

    function test_getExtensionArgs_alias_if_available()
    {
        $attr = new Auth_OpenID_AX_AttrInfo(
                                            'type://of.transportation', 1, false,
                                            'transport');

        $this->msg->add($attr);
        $this->failUnlessExtensionArgs(array(
            'type.' .  $attr->alias => $attr->type_uri,
            'if_available' => $attr->alias));
    }

    function test_getExtensionArgs_alias_req()
    {
        $attr = new Auth_OpenID_AX_AttrInfo(
            'type://of.transportation',
            1, true, 'transport');

        $this->msg->add($attr);
        $this->failUnlessExtensionArgs(array(
            'type.' . $attr->alias => $attr->type_uri,
            'required' => $attr->alias));
    }

    /*
     * Make sure that getExtensionArgs has the expected result
     *
     * This method will fill in the mode.
     */
    function failUnlessExtensionArgs($expected_args)
    {
        $expected_args['mode'] = $this->msg->mode;
        $this->assertEquals($expected_args, $this->msg->getExtensionArgs());
    }

    function test_isIterable()
    {
        $this->assertEquals(array(), $this->msg->iterAttrs());
        $this->assertEquals(array(), $this->msg->iterTypes());
    }

    function test_getRequiredAttrs_empty()
    {
        $this->assertEquals(array(), $this->msg->getRequiredAttrs());
    }

    function test_parseExtensionArgs_extraType()
    {
        $extension_args = array(
                                'mode' => 'fetch_request',
                                'type.' . $this->alias_a => $this->type_a);

        $this->assertTrue(Auth_OpenID_AX::isError(
               $this->msg->parseExtensionArgs($extension_args)));
    }

    function test_parseExtensionArgs()
    {
        $extension_args = array(
            'mode' => 'fetch_request',
            'type.' . $this->alias_a => $this->type_a,
            'if_available' => $this->alias_a);

        $this->msg->parseExtensionArgs($extension_args);
        $this->assertEquals(array($this->type_a), $this->msg->iterTypes());
        $attr_info = Auth_OpenID::arrayGet($this->msg->requested_attributes,
                                           $this->type_a);
        $this->assertTrue($attr_info);
        $this->assertFalse($attr_info->required);
        $this->assertEquals($this->type_a, $attr_info->type_uri);
        $this->assertEquals($this->alias_a, $attr_info->alias);
        $this->assertEquals(array($attr_info),
                            $this->msg->iterAttrs());
    }

    function test_extensionArgs_idempotent()
    {
        $extension_args = array(
            'mode' => 'fetch_request',
            'type.' . $this->alias_a => $this->type_a,
            'if_available' => $this->alias_a);

        $this->msg->parseExtensionArgs($extension_args);
        $this->assertEquals($extension_args, $this->msg->getExtensionArgs());

        $attr = $this->msg->requested_attributes[$this->type_a];
        $this->assertFalse($attr->required);
    }

    function test_extensionArgs_idempotent_count_required()
    {
        $extension_args = array(
                                'mode' => 'fetch_request',
                                'type.' . $this->alias_a => $this->type_a,
                                'count.' . $this->alias_a => '2',
                                'required' => $this->alias_a);

        $this->msg->parseExtensionArgs($extension_args);
        $this->assertEquals($extension_args, $this->msg->getExtensionArgs());

        $attr = $this->msg->requested_attributes[$this->type_a];
        $this->assertTrue($attr->required);
    }

    function test_extensionArgs_count1()
    {
        $extension_args = array(
            'mode' => 'fetch_request',
            'type.' . $this->alias_a => $this->type_a,
            'count.' . $this->alias_a => '1',
            'if_available' => $this->alias_a);

        $extension_args_norm = array(
            'mode' => 'fetch_request',
            'type.' . $this->alias_a => $this->type_a,
            'if_available' => $this->alias_a);

        $this->msg->parseExtensionArgs($extension_args);
        $this->assertEquals($extension_args_norm, $this->msg->getExtensionArgs());
    }
}

class FetchResponseTest extends PHPUnit_TestCase {
    function setUp()
    {
        $this->msg = new Auth_OpenID_AX_FetchResponse();
        $this->value_a = 'monkeys';
        $this->type_a = 'http://phone.home/';
        $this->alias_a = 'robocop';
    }

    function test_construct()
    {
        $this->assertTrue($this->msg->update_url === null);
        $this->assertEquals(array(), $this->msg->data);
    }

    function test_getExtensionArgs_empty()
    {
        $expected_args = array(
            'mode' => 'fetch_response',
                               );
        $req = null;
        $this->assertEquals($expected_args, $this->msg->getExtensionArgs($req));
    }

    function test_getExtensionArgs_empty_request()
    {
        $expected_args = array(
            'mode' => 'fetch_response',
                               );
        $req = new Auth_OpenID_AX_FetchRequest();
        $this->assertEquals($expected_args, $this->msg->getExtensionArgs($req));
    }

    function test_getExtensionArgs_empty_request_some()
    {
        $expected_args = array(
            'mode' => 'fetch_response',
                               );
        $req = new Auth_OpenID_AX_FetchRequest();
        $req->add(new Auth_OpenID_AX_AttrInfo('http://not.found/'));
        $this->assertEquals($expected_args, $this->msg->getExtensionArgs($req));
    }

    function test_getExtensionArgs_some_request()
    {
        $expected_args = array(
            'mode' => 'fetch_response',
            'type.' . $this->alias_a => $this->type_a,
            'value.' . $this->alias_a => $this->value_a,
                               );
        $req = new Auth_OpenID_AX_FetchRequest();
        $req->add(new Auth_OpenID_AX_AttrInfo($this->type_a, 1, false, $this->alias_a));
        $this->msg->addValue($this->type_a, $this->value_a);

        $result = $this->msg->getExtensionArgs($req);
        $this->assertEquals($expected_args, $result);
    }

    function test_getExtensionArgs_some_not_request()
    {
        $req = new Auth_OpenID_AX_FetchRequest();
        $this->msg->addValue($this->type_a, $this->value_a);
        $this->assertTrue(Auth_OpenID_AX::isError($this->msg->getExtensionArgs($req)));
    }

    function test_getSingle_success()
    {
        $req = new Auth_OpenID_AX_FetchRequest();
        $this->msg->addValue($this->type_a, $this->value_a);
        $this->assertEquals($this->value_a, $this->msg->getSingle($this->type_a));
    }

    function test_getSingle_none()
    {
        $this->assertEquals(null, $this->msg->getSingle($this->type_a));
    }

    function test_getSingle_extra()
    {
        $data = array('x', 'y');
        $this->msg->setValues($this->type_a, $data);
        $this->assertTrue(Auth_OpenID_AX::isError($this->msg->getSingle($this->type_a)));
    }

    function test_get()
    {
      $this->assertTrue(Auth_OpenID_AX::isError($this->msg->get($this->type_a)));
    }
}

class StoreRequestTest extends PHPUnit_TestCase {
    function setUp()
    {
        $this->msg = new Auth_OpenID_AX_StoreRequest();
        $this->type_a = 'http://three.count/';
        $this->alias_a = 'juggling';
    }

    function test_construct()
    {
        $this->assertEquals(array(), $this->msg->data);
    }

    function test_getExtensionArgs_empty()
    {
        $args = $this->msg->getExtensionArgs();
        $expected_args = array(
            'mode' => 'store_request',
                               );
        $this->assertEquals($expected_args, $args);
    }

    function test_getExtensionArgs_nonempty()
    {
        $data = array('foo', 'bar');
        $this->msg->setValues($this->type_a, $data);
        $aliases = new Auth_OpenID_NamespaceMap();
        $aliases->addAlias($this->type_a, $this->alias_a);
        $args = $this->msg->getExtensionArgs($aliases);
        $expected_args = array(
            'mode' => 'store_request',
            'type.' . $this->alias_a => $this->type_a,
            'count.' . $this->alias_a => '2',
            sprintf('value.%s.1', $this->alias_a) => 'foo',
            sprintf('value.%s.2', $this->alias_a) => 'bar',
                               );
        $this->assertEquals($expected_args, $args);
    }
}

class StoreResponseTest extends PHPUnit_TestCase {
    function test_success()
    {
        $msg = new Auth_OpenID_AX_StoreResponse();
        $this->assertTrue($msg->succeeded());
        $this->assertFalse($msg->error_message);
        $this->assertEquals(array('mode' => 'store_response_success'),
                            $msg->getExtensionArgs());
    }

    function test_fail_nomsg()
    {
        $msg = new Auth_OpenID_AX_StoreResponse(false);
        $this->assertFalse($msg->succeeded());
        $this->assertFalse($msg->error_message);
        $this->assertEquals(array('mode' => 'store_response_failure'),
                            $msg->getExtensionArgs());
    }

    function test_fail_msg()
    {
        $reason = 'no reason, really';
        $msg = new Auth_OpenID_AX_StoreResponse(false, $reason);
        $this->assertFalse($msg->succeeded());
        $this->assertEquals($reason, $msg->error_message);
        $this->assertEquals(array('mode' => 'store_response_failure',
                                  'error' => $reason), $msg->getExtensionArgs());
    }
}

class Tests_Auth_OpenID_AX extends PHPUnit_TestSuite {
    function getName()
    {
        return "Tests_Auth_OpenID_AX";
    }

    function Tests_Auth_OpenID_AX()
    {
        $this->addTestSuite('StoreResponseTest');
        $this->addTestSuite('StoreRequestTest');
        $this->addTestSuite('FetchResponseTest');
        $this->addTestSuite('FetchRequestTest');
        $this->addTestSuite('ParseAXValuesTest');
        $this->addTestSuite('ToTypeURIsTest');
        $this->addTestSuite('AttrInfoTest');
        $this->addTestSuite('AXMessageTest');
    }
}

?>