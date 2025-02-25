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

namespace Pimcore\Bundle\AdminBundle\Controller\Admin\Document;

use Pimcore\Controller\Traits\ElementEditLockHelperTrait;
use Pimcore\Model\Document;
use Pimcore\Model\Element;
use Pimcore\Model\Schedule\Task;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/snippet")
 *
 * @internal
 */
class SnippetController extends DocumentControllerBase
{
    use ElementEditLockHelperTrait;

    /**
     * @Route("/save-to-session", name="pimcore_admin_document_snippet_savetosession", methods={"POST"})
     *
     * {@inheritDoc}
     */
    public function saveToSessionAction(Request $request)
    {
        return parent::saveToSessionAction($request);
    }

    /**
     * @Route("/remove-from-session", name="pimcore_admin_document_snippet_removefromsession", methods={"DELETE"})
     *
     * {@inheritDoc}
     */
    public function removeFromSessionAction(Request $request)
    {
        return parent::removeFromSessionAction($request);
    }

    /**
     * @Route("/change-master-document", name="pimcore_admin_document_snippet_changemasterdocument", methods={"PUT"})
     *
     * {@inheritDoc}
     */
    public function changeMasterDocumentAction(Request $request)
    {
        return parent::changeMasterDocumentAction($request);
    }

    /**
     * @Route("/get-data-by-id", name="pimcore_admin_document_snippet_getdatabyid", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getDataByIdAction(Request $request)
    {
        $snippet = Document\Snippet::getById($request->get('id'));

        if (!$snippet) {
            throw $this->createNotFoundException('Snippet not found');
        }

        // check for lock
        if ($snippet->isAllowed('save') || $snippet->isAllowed('publish') || $snippet->isAllowed('unpublish') || $snippet->isAllowed('delete')) {
            if (Element\Editlock::isLocked($request->get('id'), 'document')) {
                return $this->getEditLockResponse($request->get('id'), 'document');
            }
            Element\Editlock::lock($request->get('id'), 'document');
        }

        $snippet = clone $snippet;
        $draftVersion = null;
        $snippet = $this->getLatestVersion($snippet, $draftVersion);

        $versions = Element\Service::getSafeVersionInfo($snippet->getVersions());
        $snippet->setVersions(array_splice($versions, -1, 1));
        $snippet->setLocked($snippet->isLocked());
        $snippet->setParent(null);

        // unset useless data
        $snippet->setEditables(null);

        $data = $snippet->getObjectVars();

        $this->addTranslationsData($snippet, $data);
        $this->minimizeProperties($snippet, $data);

        $data['url'] = $snippet->getUrl();
        $data['scheduledTasks'] = array_map(
            static function (Task $task) {
                return $task->getObjectVars();
            },
            $snippet->getScheduledTasks()
        );

        if ($snippet->getContentMasterDocument()) {
            $data['contentMasterDocumentPath'] = $snippet->getContentMasterDocument()->getRealFullPath();
        }

        $this->preSendDataActions($data, $snippet, $draftVersion);

        if ($snippet->isAllowed('view')) {
            return $this->adminJson($data);
        }

        throw $this->createAccessDeniedHttpException();
    }

    /**
     * @Route("/save", name="pimcore_admin_document_snippet_save", methods={"POST","PUT"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function saveAction(Request $request)
    {
        $snippet = Document\Snippet::getById($request->get('id'));

        if (!$snippet) {
            throw $this->createNotFoundException('Snippet not found');
        }

        /** @var Document\Snippet|null $snippetSession */
        $snippetSession = $this->getFromSession($snippet);

        if ($snippetSession) {
            $snippet = $snippetSession;
        } else {
            $snippet = $this->getLatestVersion($snippet);
        }

        $snippet->setUserModification($this->getAdminUser()->getId());

        if ($request->get('task') == 'unpublish') {
            $snippet->setPublished(false);
        }
        if ($request->get('task') == 'publish') {
            $snippet->setPublished(true);
        }

        if (($request->get('task') == 'publish' && $snippet->isAllowed('publish')) || ($request->get('task') == 'unpublish' && $snippet->isAllowed('unpublish'))) {
            $this->setValuesToDocument($request, $snippet);

            $snippet->save();
            $this->saveToSession($snippet);

            $treeData = $this->getTreeNodeConfig($snippet);

            $this->handleTask($request->get('task'), $snippet);

            return $this->adminJson([
                'success' => true,
                'data' => [
                    'versionDate' => $snippet->getModificationDate(),
                    'versionCount' => $snippet->getVersionCount(),
                ],
                'treeData' => $treeData,
            ]);
        } elseif ($snippet->isAllowed('save')) {
            $this->setValuesToDocument($request, $snippet);

            $version = $snippet->saveVersion(true, true, null, $request->get('task') == 'autoSave');
            $this->saveToSession($snippet);

            $draftData = [
                'id' => $version->getId(),
                'modificationDate' => $version->getDate(),
            ];

            $this->handleTask($request->get('task'), $snippet);

            return $this->adminJson(['success' => true, 'draft' => $draftData]);
        } else {
            throw $this->createAccessDeniedHttpException();
        }
    }

    /**
     * @param Request $request
     * @param Document $snippet
     */
    protected function setValuesToDocument(Request $request, Document $snippet)
    {
        $this->addSettingsToDocument($request, $snippet);
        $this->addDataToDocument($request, $snippet);
        $this->applySchedulerDataToElement($request, $snippet);
        $this->addPropertiesToDocument($request, $snippet);
    }
}
