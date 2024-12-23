<?php
include "panel/db.php";
?>

<?php
	## 2. ADIM için örnek kodlar ##

	## ÖNEMLİ UYARILAR ##
	## 1) Bu sayfaya oturum (SESSION) ile veri taşıyamazsınız. Çünkü bu sayfa müşterilerin yönlendirildiği bir sayfa değildir.
	## 2) Entegrasyonun 1. ADIM'ında gönderdiğniz merchant_oid değeri bu sayfaya POST ile gelir. Bu değeri kullanarak
	## veri tabanınızdan ilgili siparişi tespit edip onaylamalı veya iptal etmelisiniz.
	## 3) Aynı sipariş için birden fazla bildirim ulaşabilir (Ağ bağlantı sorunları vb. nedeniyle). Bu nedenle öncelikle
	## siparişin durumunu veri tabanınızdan kontrol edin, eğer onaylandıysa tekrar işlem yapmayın. Örneği aşağıda bulunmaktadır.

	$post = $_POST;

	####################### DÜZENLEMESİ ZORUNLU ALANLAR #######################
	#
	## API Entegrasyon Bilgileri - Mağaza paneline giriş yaparak BİLGİ sayfasından alabilirsiniz.
	#$merchant_id 	= ''; Db den çekiliyor
	#$merchant_key 	= ''; Db den çekiliyor
	#$merchant_salt	= ''; Db den çekiliyor
	#
	###########################################################################

	####### Bu kısımda herhangi bir değişiklik yapmanıza gerek yoktur. #######
	#
	## POST değerleri ile hash oluştur.
	$hash = base64_encode( hash_hmac('sha256', $post['merchant_oid'].$merchant_salt.$post['status'].$post['total_amount'], $merchant_key, true) );
	#
	## Oluşturulan hash'i, paytr'dan gelen post içindeki hash ile karşılaştır (isteğin paytr'dan geldiğine ve değişmediğine emin olmak için)
	## Bu işlemi yapmazsanız maddi zarara uğramanız olasıdır.
	if( $hash != $post['hash'] )
		die('PAYTR notification failed: bad hash');
	###########################################################################

	## BURADA YAPILMASI GEREKENLER
	## 1) Siparişin durumunu $post['merchant_oid'] değerini kullanarak veri tabanınızdan sorgulayın.
	## 2) Eğer sipariş zaten daha önceden onaylandıysa veya iptal edildiyse  echo "OK"; exit; yaparak sonlandırın.

	/* Sipariş durum sorgulama örnek
 	   $durum = SQL
	   if($durum == "onay" || $durum == "iptal"){
			echo "OK";
			exit;
		}
	 */
$siparisno=$post['merchant_oid'];
$sipbak = $db->prepare("SELECT * FROM ucretler WHERE siparisno=:siparisno");
$sipbak->bindValue(':siparisno', $siparisno);
$sipbak->execute();
if($sipbak->rowCount()){
$sip = $sipbak->fetch(PDO::FETCH_ASSOC);
$uyeid=$sip['uyeid'];
$miktar=$sip['miktar'];
$paketid=$sip['paketid'];
}




	if( $post['status'] == 'success' ) { ## Ödeme Onaylandı

		## BURADA YAPILMASI GEREKENLER
		## 1) Siparişi onaylayın.
		## 2) Eğer müşterinize mesaj / SMS / e-posta gibi bilgilendirme yapacaksanız bu aşamada yapmalısınız.
		## 3) 1. ADIM'da gönderilen payment_amount sipariş tutarı taksitli alışveriş yapılması durumunda
		## değişebilir. Güncel tutarı $post['total_amount'] değerinden alarak muhasebe işlemlerinizde kullanabilirsiniz.


if($paketid=='0'){
$bak = $db->prepare("SELECT * FROM uyeler WHERE id=:uyeid");
$bak->bindValue(':uyeid', $uyeid);
$bak->execute();
if($bak->rowCount()){
$uyebilgi = $bak->fetch(PDO::FETCH_ASSOC);
$uyebakiye = $uyebilgi['bakiye'];
}

$yenibakiye = round(($uyebakiye + $miktar),2);
$song1 = $db->prepare("UPDATE uyeler SET bakiye = :yenibakiye where id=:uyeid ");
$song1->bindValue(':uyeid', $uyeid);
$song1->bindValue(':yenibakiye', $yenibakiye);
$song1->execute();
}

$song1 = $db->prepare("UPDATE ucretler SET durum = 1 where siparisno=:siparisno ");
$song1->bindValue(':siparisno', $siparisno);
$song1->execute();


echo "OK";
exit;

	} else { ## Ödemeye Onay Verilmedi

		## BURADA YAPILMASI GEREKENLER
		## 1) Siparişi iptal edin.
		## 2) Eğer ödemenin onaylanmama sebebini kayıt edecekseniz aşağıdaki değerleri kullanabilirsiniz.
		## $post['failed_reason_code'] - başarısız hata kodu
		## $post['failed_reason_msg'] - başarısız hata mesajı
		
$song1 = $db->prepare("UPDATE ucretler SET durum = 2 where siparisno=:siparisno ");
$song1->bindValue(':siparisno', $siparisno);
$song1->execute();


echo "OK";
exit;

	}

	## Bildirimin alındığını PayTR sistemine bildir.

?>