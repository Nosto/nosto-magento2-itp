<?php /** @noinspection PhpUnused */
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Itp\Observer\App\Action;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Logger\Logger;

class Action implements ObserverInterface
{
    /**
     * Name of cookie that holds Nosto visitor id
     */
    const COOKIE_NAME = '2c_cId';

    /**
     * Name of cookie that holds Nosto visitor id dot separated
     * PHP Can't set if uses underscores.
     */
    const COOKIE_NAME_SET = '2c.cId';

    /**
     * Name of cookie used for Webkit's ITP prevention
     */
    const HTTP_COOKIE_NAME = '2c_cId_http';

    /**
     * Name of cookie used for Webkit's ITP prevention
     * PHP Can't set if underscores are used.
     */
    const HTTP_COOKIE_NAME_SET = '2c.cId.http';

    /** @var CookieManagerInterface */
    private CookieManagerInterface $cookieManager;

    /** @var StoreManagerInterface */
    private StoreManagerInterface $storeManager;

    /** @var Logger */
    private Logger $logger;

    /**
     * Action constructor.
     * @param CookieManagerInterface $cookieManager
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        StoreManagerInterface $storeManager,
        Logger $logger
    ) {
        $this->cookieManager = $cookieManager;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     * @throws InputException
     * @suppress PhanUndeclaredMethod
     */
    public function execute(Observer $observer) // @codingStandardsIgnoreLine
    {
        $cookieValue = $this->cookieManager->getCookie(self::COOKIE_NAME);
        $cookieValueHttp = $this->cookieManager->getCookie(self::HTTP_COOKIE_NAME);
        if ((empty($cookieValue) && empty($cookieValueHttp)) || ($cookieValue === $cookieValueHttp)) {
            return;
        }
        $cookieMetadata = new PublicCookieMetadata();
        $cookieMetadata->setDuration(3600 * 24 * (365 * 2)); // 2 Years
        $cookieMetadata->setPath('/');
        try {
            if ($this->storeManager->getStore()->isFrontUrlSecure()) {
                $cookieMetadata->setSecure(true);
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->error(
                sprintf(
                    'Could not get store while setting cookie. Message was %s',
                    $e->getMessage()
                )
            );
        }
        if (!empty($cookieValue) && $cookieValueHttp !== $cookieValue) {
            $cookieMetadata->setHttpOnly(true);
            $this->cookieManager->setPublicCookie(
                self::HTTP_COOKIE_NAME_SET,
                $cookieValue,
                $cookieMetadata
            );
        } elseif (!empty($cookieValueHttp)) {
            // In case 2c.cId changes on the client side, means that the cookie has been deleted.
            // We should update 2c.cId with 2c.cId.http
            $cookieMetadata->setHttpOnly(false);
            $this->cookieManager->setPublicCookie(
                self::COOKIE_NAME_SET,
                $cookieValueHttp,
                $cookieMetadata
            );
        }
    }
}
