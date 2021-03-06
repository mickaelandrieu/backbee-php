<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\Rest\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Validator\Constraints as Assert;

use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Security\Token\BBUserToken;

/**
 * Auth Controller.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class SecurityController extends AbstractRestController
{
    /**
     * @Rest\RequestParam(name="username", requirements={@Assert\NotBlank})
     * @Rest\RequestParam(name="password", requirements={@Assert\NotBlank})
     */
    public function authenticateAction(Request $request)
    {
        $created = date('Y-m-d H:i:s');
        $token = new BBUserToken();
        $token->setUser($request->request->get('username'));
        $token->setCreated($created);
        $token->setNonce(md5(uniqid('', true)));
        $encodedPassword = $this
            ->getApplication()
            ->getSecurityContext()
            ->getEncoderFactory()
            ->getEncoder('BackBee\Security\User')
            ->encodePassword($request->request->get('password'), '')
        ;

        $token->setDigest(md5($token->getNonce().$created.$encodedPassword));

        $tokenAuthenticated = $this->getApplication()->getSecurityContext()->getAuthenticationManager()
            ->authenticate($token)
        ;

        if (!$tokenAuthenticated->getUser()->getApiKeyEnabled()) {
            throw new DisabledException('API access forbidden');
        }

        $this->getApplication()->getSecurityContext()->setToken($tokenAuthenticated);

        return $this->createJsonResponse(null, 201, array(
            'X-API-KEY'       => $tokenAuthenticated->getUser()->getApiKeyPublic(),
            'X-API-SIGNATURE' => $tokenAuthenticated->getNonce(),
        ));
    }

    /**
     * @Rest\Security(expression="is_fully_authenticated()")
     */
    public function deleteSessionAction(Request $request)
    {
        if (null === $request->getSession()) {
            throw new NotFoundHttpException('Session doesn\'t exist');
        }

        $event = new GetResponseEvent(
            $this->getApplication()->getController(),
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );
        $this->getApplication()->getEventDispatcher()->dispatch('frontcontroller.request.logout', $event);

        return new Response('', 204);
    }
}
