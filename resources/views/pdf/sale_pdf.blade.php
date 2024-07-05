<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8">
      <title>Sale _{{$sale['Ref']}}</title>
      <link rel="stylesheet" href="{{asset('/css/pdf_style.css')}}" media="all" />
   </head>

   <body>
      <header class="clearfix">
         <div id="logo">
         <img src="{{asset('/images/'.$setting['logo'])}}">
         </div>
         <div id="company">
            <div><strong> Date : </strong>{{$sale['date']}}</div>
            <div><strong> Number : </strong> {{$sale['Ref']}}</div>
            <div><strong> Status : </strong> {{$sale['statut']}}</div>
            <div><strong> Payment Status : </strong> {{$sale['payment_status']}}</div>
         </div>
         <div id="Title-heading">
            Sale  : {{$sale['Ref']}}
         </div>
         </div>
      </header>
      <main>
         <div id="details" class="clearfix">
            <div id="client">
               <table class="table-sm">
                  <thead>
                     <tr>
                        <th class="desc">Customer Info</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr>
                        <td>
                           @if($sale['client_name'])<div><strong>Nom complet :</strong> {{$sale['client_name']}}</div>@endif
                           @if($sale['client_phone'])<div><strong>Tél :</strong> {{$sale['client_phone']}}</div>@endif
                           @if($sale['client_email'])<div><strong>Email :</strong>  {{$sale['client_email']}}</div>@endif
                           @if($sale['client_adr'])<div><strong>Address :</strong>   {{$sale['client_adr']}}</div>@endif
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
            <div id="invoice">
               <table class="table-sm">
                  <thead>
                     <tr>
                        <th class="desc">Company Info</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr>
                        <td>
                           <div id="comp">{{$setting['CompanyName']}}</div>
                           <div><strong>Tél :</strong>  {{$setting['CompanyPhone']}}</div>
                           <div><strong>Email :</strong>  {{$setting['email']}}</div>
                           <div><strong>Addresse :</strong>  {{$setting['CompanyAdress']}}</div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
         </div>
         <div id="details_inv">
            <table  class="table-sm">
               <thead>
                  <tr>
                     <th>Produit</th>
                     <th>Prix</th>
                     <th>Qte</th>
                     <th>TOTAL</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach ($details as $detail)
                  <tr>
                     <td>
                        <span>{{$detail['code']}} ({{$detail['name']}})</span>
                           @if($detail['is_imei'] && $detail['imei_number'] !==null)
                              <p>IMEI/SN : {{$detail['imei_number']}}</p>
                           @endif
                     </td>
                     <td>{{$detail['price']}} </td>
                     <td>{{$detail['quantity']}}/{{$detail['unitSale']}}</td>
                     <td>{{$detail['total']}} </td>
                  </tr>
                  @endforeach
               </tbody>
            </table>
         </div>
         <div id="total">
            <table>
               <tr>
                  <td>Remise</td>
                  <td>{{$sale['discount']}} </td>
               </tr>
               <tr>
                  <td>Transport</td>
                  <td>{{$sale['shipping']}} </td>
               </tr>
               <tr>
                  <td>Total</td>
                  <td>{{$symbol}} {{$sale['GrandTotal']}} </td>
               </tr>

               <tr>
                  <td>Montant payé</td>
                  <td>{{$symbol}} {{$sale['paid_amount']}} </td>
               </tr>

               <tr>
                  <td>Montant restant</td>
                  <td>{{$symbol}} {{$sale['due']}} </td>
               </tr>

               <tr>
                  <td>Reste crédit</td>
                  <td>{{$symbol}} {{$sale['total_credit']}} </td>
               </tr>
            </table>
         </div>
      </main>
   </body>
</html>
