$("body").on("click","[action='showLostPasswordBox']",function(e){$("#lostPasswordForm").show();$("#loginForm").hide()});$("body").on("click","[action='showLoginBox']",function(e){$("#lostPasswordForm").hide();$("#loginForm").show()});$("body").on("click","[action='login']",function(e){$("#ajaxGlobalLoader").show();$("#formLogin").submit()});$("body").on("click","[action='reinitPassword']",function(e){var xhrPath=getXhrPath(ROUTE_SECURITY_LOST_PASSWORD_CHECK,"security","lostPasswordCheck",RETURN_BOOLEAN);$.ajax({type:"POST",url:xhrPath,data:$("#formLostPassword").serialize(),dataType:"json",beforeSend:function(xhr){xhrBeforeSend(xhr,1)},statusCode:{404:function(){xhr404()},500:function(){xhr500()}},error:function(jqXHR,textStatus,errorThrown){xhrError(jqXHR,textStatus,errorThrown)},success:function(data){$("#ajaxGlobalLoader").hide();if(data["error"]){$("#infoBoxHolder .boxError .notifBoxText").html(data["error"]);$("#infoBoxHolder .boxError").show()}else{$("[action='modalClose']").trigger("click");$("#infoBoxHolder .boxSuccess .notifBoxText").html("Un nouveau mot de passe vient de vous être envoyé par email.");$("#infoBoxHolder .boxSuccess").show()}}})});