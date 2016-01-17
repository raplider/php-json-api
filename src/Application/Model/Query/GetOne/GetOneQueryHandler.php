<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 12/2/15
 * Time: 9:37 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Api\JsonApi\Application\Query\GetOne;

use Exception;
use NilPortugues\Api\JsonApi\Domain\Model\Contracts\ResourceRepository;
use NilPortugues\Api\JsonApi\Domain\Model\Contracts\MappingRepository;
use NilPortugues\Api\JsonApi\Domain\Model\Errors\Error;
use NilPortugues\Api\JsonApi\Domain\Model\Errors\ErrorBag;
use NilPortugues\Api\JsonApi\Domain\Model\Errors\NotFoundError;
use NilPortugues\Api\JsonApi\Http\Request\Parameters\Fields;
use NilPortugues\Api\JsonApi\Http\Request\Parameters\Included;
use NilPortugues\Api\JsonApi\Http\Request\Parameters\Sorting;
use NilPortugues\Api\JsonApi\JsonApiSerializer;
use NilPortugues\Api\JsonApi\Server\Actions\Traits\RequestTrait;
use NilPortugues\Api\JsonApi\Server\Exceptions\InputException;
use NilPortugues\Api\JsonApi\Server\Query\GetAssertion;

class GetOneQueryHandler
{
    use RequestTrait;

    /**
     * @var \NilPortugues\Api\JsonApi\Domain\Model\Errors\ErrorBag
     */
    protected $errorBag;

    /**
     * @var JsonApiSerializer
     */
    protected $serializer;

    /**
     * @var Fields
     */
    protected $fields;

    /**
     * @var Included
     */
    protected $included;
    /**
     * @var ResourceRepository
     */
    protected $actionRepository;
    /**
     * @var MappingRepository
     */
    protected $mappingRepository;

    /**
     * GetResourceHandler constructor.
     *
     * @param MappingRepository  $mappingRepository
     * @param ResourceRepository $actionRepository
     * @param JsonApiSerializer  $serializer
     * @param Fields             $fields
     * @param Included           $included
     */
    public function __construct(
        MappingRepository $mappingRepository,
        ResourceRepository $actionRepository,
        JsonApiSerializer $serializer,
        Fields $fields,
        Included $included
    ) {
        $this->mappingRepository = $mappingRepository;
        $this->actionRepository = $actionRepository;
        $this->serializer = $serializer;
        $this->errorBag = new ErrorBag();
        $this->fields = $fields;
        $this->included = $included;
    }

    /**
     * @param GetOneQuery $resource
     *
     * @return GetOneResponse
     */
    public function __invoke(GetOneQuery $resource)
    {
        try {
            GetAssertion::assert(
                $this->fields,
                $this->included,
                new Sorting(),
                $this->errorBag,
                $resource->className()
            );

            $data = $this->actionRepository->find($resource->id());

            if (empty($data)) {
                $mapping = $this->mappingRepository->findByClassName($resource->className());

                return $this->resourceNotFound(
                    new ErrorBag([new NotFoundError($mapping->getClassAlias(), $resource->id())])
                );
            }

            $response = $this->response($this->serializer->serialize($data, $this->fields, $this->included));
        } catch (Exception $e) {
            $response = $this->getErrorResponse($e);
        }

        return $response;
    }

    /**
     * @param Exception $e
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getErrorResponse(Exception $e)
    {
        switch (get_class($e)) {
            case InputException::class:
                $response = $this->errorResponse($this->errorBag);
                break;

            default:
                $response = $this->errorResponse(
                    new ErrorBag([new Error('Bad Request', 'Request could not be served.')])
                );
        }

        return $response;
    }
}
