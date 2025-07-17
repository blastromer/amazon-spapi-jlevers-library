<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoTyphoeusBaseModel;

class AmazonKitsMongo extends MongoTyphoeusBaseModel
{
    /**
     * The database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mongodb_typhoeus_conn';

    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection = 'amz_kits';

    public function hasKit($id) {
		try {
			$data = self::where(["productId" => $id])->first();
			if(!empty($data)) return true;
			return false;
		}
		catch (Exception $e) {
			dump($e);
			return false;
		}
	}

	public function getComponents($kitId) {
		try {
			$data = self::where(["productId" => $kitId])->first();

			if(!empty($data->components)) return $data->components;
			return false;
		}
		catch (Exception $e) {
			dump($e);
			return false;
		}
	}

	public function setComponents($kitId, $kitComponentArray) {
		try {
			$data = self::where(["productId" => $kitId])->first();
			if(empty($data)) {
				$data = App::make("AmazonKitsMongo");
				$data->productId = $kitId;
			}
			$data->components = array_unique($kitComponentArray);
			$data->save();
			return true;

		}
		catch (Exception $e) {
			dump($e);
			return false;
		}
	}
}