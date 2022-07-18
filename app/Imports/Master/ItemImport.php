<?php

namespace App\Imports\Master;

use App\Model\Master\Item;
use App\Model\Master\ItemGroup;
use App\Model\Accounting\ChartOfAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ItemImport implements  ToModel, SkipsOnError, SkipsOnFailure, WithValidation, WithStartRow
{
  private $success = 0;
  // private $fail = 0;
  // private $start_row = 0;

  use Importable, SkipsErrors, SkipsFailures;

  public function model(array $row)
  {
    $item = $this->generateItem($row);
    //check chart of account
    $accounts = ChartOfAccount::select('id')->where('alias', strtoupper($item['chart_of_account']))->first();
    if (isset($accounts->id)) {
      $item['chart_of_account_id'] = $accounts->id;
      if (isset($item['group_name'])) {
        $itemGroup = ItemGroup::select('id', 'name')->where('name', $item['group_name'])->get();
        if ($itemGroup->isEmpty()) {
          $itemGroup = new ItemGroup();
          $itemGroup->name = $item['group_name'];
          $itemGroup->save();
          $itemGroup = [$itemGroup];
        }
        $item['groups'] = $itemGroup;
      }
      $this->success += 1;
      return new Item($item);
    } 
  }

  // public function collection(Collection $rows)
  // {
  //   $index = 0;

  //   foreach ($rows as $row) {
  //     $index++;
  //     if($index <= $this->start_row)  {
  //       continue;
  //     }
  //     if ($row[request()->get("name")] != null && $row[request()->get("code")] != null && $row[request()->get("chart_of_account")] != null) {
  //         //set item
  //         $item = $this->generateItem($row);    
  //         //check chart of account
  //         $accounts = ChartOfAccount::select('id')->where('alias', strtoupper($item['chart_of_account']))->first();
  //         if(isset($accounts->id)){
  //             $item['chart_of_account_id'] = $accounts->id;
  //               if(isset($item['group_name'])){
  //                   $itemGroup = ItemGroup::select('id','name')->where('name', $item['group_name'])->get();
  //                   if($itemGroup->isEmpty()){
  //                       $itemGroup = new ItemGroup();
  //                       $itemGroup->name = $item['group_name'];
  //                       $itemGroup->save();
  //                       $itemGroup = [$itemGroup];
  //                   }
  //                   $item['groups'] = $itemGroup;
  //               }
  //               $save = Item::create($item);
  //               $this->success++;
  //         }else{
  //             $this->fail++;
  //         }
  //     }else{
  //       $this->fail++; 
  //     }
  //   }
  // }

  public function generateItem($row)
  {
    $item['code'] = $row[request()->get("code")];
    $item['name'] = $row[request()->get("name")];
    $item['chart_of_account'] = $row[request()->get("chart_of_account")];
    $item['units'] = [];
    if(request()->get("units_measurement_1") != null){
        if($row[request()->get("units_measurement_1")] != null){
            array_push($item['units'], $this->generateUnits($row[request()->get("units_measurement_1")],1));
        }
    }
    if(request()->get("units_converter_2") != null && request()->get("units_measurement_2") != null){
      if($row[request()->get("units_converter_2")] != null && $row[request()->get("units_measurement_2")] != null){
          array_push($item['units'], $this->generateUnits($row[request()->get("units_measurement_2")],$row[request()->get("units_converter_2")]));
      }
    }
    if(empty($item['units'])){
      array_push($item['units'], $this->generateUnits('pcs', 1));
    }
    if(request()->get("require_expiry_date") != null && $row[request()->get("require_expiry_date")]!= null){
      $item['require_expiry_date'] = $row[request()->get("require_expiry_date")] == "true";
    }
    if(request()->get("require_production_number") != null && $row[request()->get("require_production_number")]!= null){
      $item['require_production_number'] = $row[request()->get("require_production_number")] == "true";
    }
    if(request()->get("group_name") != null && $row[request()->get("group_name")]!= null){
      $item['group_name'] = $row[request()->get("group_name")];
    }
    return $item;
  }

  public function generateUnits($measurement, $converter)
  {
    return [
        "label" => $measurement,
        "name" => $measurement,
        "converter" => $converter,
        "default_purchase" => false,
        "default_sales" => false
    ];
  }

  public function startRow(): int
  {
    return request()->get("start_row");
  }

  public function rules(): array
  {
    return [
      '*.code' => ['unique:items,code']
    ];
  }

  public function getSuccess()
  {
    return $this->success;
  }

}
