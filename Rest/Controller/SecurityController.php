<?php

/*
 * Copyright (c) 2011-2013 Lp digital system
 * 
 * This file is part of BackBuilder5.
 *
 * BackBuilder5 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * BackBuilder5 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with BackBuilder5. If not, see <http://www.gnu.org/licenses/>.
 */

namespace BackBuilder\Rest\Controller;

use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\Validator\ConstraintViolationList;

use BackBuilder\Rest\Controller\Annotations as Rest;
use Symfony\Component\Validator\Constraints as Assert;

use BackBuilder\Security\Token\BBUserToken,
    BackBuilder\Security\Exception\SecurityException;

use BackBuilder\Rest\Exception\ValidationException;

/**
 * Auth Controller
 *
 * @category    BackBuilder
 * @package     BackBuilder\Rest
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class SecurityController extends ARestController 
{
   
    /**
     * Authenticate against a specific firewall
     * 
     * Note: request attributes as well as the request format depend on the 
     * specific implementation of the firewall and its provider
     * 
     * 
     * @Rest\RequestParam(name = "firewall", description="Firewall to authenticate against", requirements = {
     *  @Assert\Choice(choices = {"bb_area"}, message="The requested firewall is invalid"), 
     * })
     * 
     */
    public function authenticateAction($firewall, Request $request, ConstraintViolationList $violations = null) 
    {
        if(null !== $violations && count($violations) > 0) {
            throw new ValidationException($violations);
        }
        
        $securityConfig = $this->getApplication()->getConfig()->getSection('security');
        
        if(!isset($securityConfig['firewalls'][$firewall])) {
            $response = new Response();
            $response->setStatusCode(400, sprintf('Firewall not configured: %s', $firewall));
            return $response;
        }
        
        $firewallConfig = $securityConfig['firewalls'][$firewall];
        
        $contexts = array_intersect(array_keys($firewallConfig), array('bb_auth') );
        
        if(0 === count($contexts)) {
            $response = new Response();
            $response->setStatusCode(400, sprintf('No supported security contexts found for firewall: %s', $firewall));
            return $response;
        }
        
        $securityContext = $this->_application->getSecurityContext();
        
        
        $response = new Response();
        
        if(in_array('bb_auth', $contexts)) {
            $username = $request->request->get('username');
            $created = $request->request->get('created');
            $nonce = $request->request->get('nonce');
            $digest = $request->request->get('digest');
            
            $token = new BBUserToken();
            $token->setUser($username);
            $token->setCreated($created);
            $token->setNonce($nonce);
            $token->setDigest($digest);
            
            $authProvider = $securityContext->getAuthProvider('bb_auth');
            

            $authProvider->authenticate($token);

            $response->setContent(json_encode(array(
                'nonce' => $nonce
            )));
            
        }
        
        return $response;
    }
    
    
    
}