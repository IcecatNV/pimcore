<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Model\Asset;

use Pimcore\Cache;
use Pimcore\Logger;
use Pimcore\Model;

/**
 * @method \Pimcore\Model\Asset\Dao getDao()
 */
class Document extends Model\Asset
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'document';

    /**
     * {@inheritdoc}
     */
    protected function update($params = [])
    {
        if ($this->getDataChanged()) {
            $this->removeCustomSetting('document_page_count');
        }

        parent::update($params);
        $this->clearThumbnails();
    }

    /**
     * @internal
     *
     * @param string|null $path
     */
    public function processPageCount($path = null)
    {
        $pageCount = null;
        if (!\Pimcore\Document::isAvailable()) {
            Logger::error("Couldn't create image-thumbnail of document " . $this->getRealFullPath() . ' no document adapter is available');

            return;
        }

        try {
            $converter = \Pimcore\Document::getInstance();
            $converter->load($this);

            // read from blob here, because in $this->update() (see above) $this->getFileSystemPath() contains the old data
            $pageCount = $converter->getPageCount();
            $this->setCustomSetting('document_page_count', $pageCount);
        } catch (\Exception $e) {
            Logger::error($e);
        }
    }

    /**
     * returns null when page count wasn't processed yet (done asynchronously)
     *
     * @return int|null
     */
    public function getPageCount()
    {
        return $this->getCustomSetting('document_page_count');
    }

    /**
     * @param string|array|Image\Thumbnail\Config $thumbnailName
     * @param int $page
     * @param bool $deferred $deferred deferred means that the image will be generated on-the-fly (details see below)
     *
     * @return Document\ImageThumbnail
     */
    public function getImageThumbnail($thumbnailName, $page = 1, $deferred = false)
    {
        if (!\Pimcore\Document::isAvailable()) {
            Logger::error("Couldn't create image-thumbnail of document " . $this->getRealFullPath() . ' no document adapter is available');

            return new Document\ImageThumbnail(null);
        }

        if (!$this->getCustomSetting('document_page_count')) {
            Logger::info('Image thumbnail not yet available, processing is done asynchronously.');
            $this->addToUpdateTaskQueue();

            return new Document\ImageThumbnail(null);
        }

        return new Document\ImageThumbnail($this, $thumbnailName, $page, $deferred);
    }

    /**
     * @param int|null $page
     *
     * @return string|null
     */
    public function getText($page = null)
    {
        if (\Pimcore\Document::isAvailable() && \Pimcore\Document::isFileTypeSupported($this->getFilename())) {
            if ($this->getCustomSetting('document_page_count')) {
                $cacheKey = 'asset_document_text_' . $this->getId() . '_' . ($page ? $page : 'all');
                if (!$text = Cache::load($cacheKey)) {
                    $document = \Pimcore\Document::getInstance();
                    $text = $document->getText($page, $this);
                    Cache::save($text, $cacheKey, $this->getCacheTags(), null, 99, true); // force cache write
                }

                return $text;
            } else {
                Logger::info('Unable to fetch text of ' . $this->getRealFullPath() . ' as it was not processed yet by the maintenance script');
            }
        } else {
            Logger::warning("Couldn't get text out of document " . $this->getRealFullPath() . ' no document adapter is available');
        }

        return null;
    }
}
