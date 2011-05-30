CKEDITOR.themes.add("default",(function(){var d={};function c(l,k){var j,i;i=l.config.sharedSpaces;i=i&&i[k];i=i&&CKEDITOR.document.getById(i);if(i){var b='<span class="cke_shared " dir="'+l.lang.dir+'"><span class="'+l.skinClass+" "+l.id+" cke_editor_"+l.name+'"><span class="'+CKEDITOR.env.cssClass+'"><span class="cke_wrapper cke_'+l.lang.dir+'"><span class="cke_editor"><div class="cke_'+k+'"></div></span></span></span></span></span>',a=i.append(CKEDITOR.dom.element.createFromHtml(b,i.getDocument()));if(i.getCustomData("cke_hasshared")){a.hide()}else{i.setCustomData("cke_hasshared",1)}j=a.getChild([0,0,0,0]);!l.sharedSpaces&&(l.sharedSpaces={});l.sharedSpaces[k]=j;l.on("focus",function(){for(var g=0,f,e=i.getChildren();f=e.getItem(g);g++){if(f.type==CKEDITOR.NODE_ELEMENT&&!f.equals(a)&&f.hasClass("cke_shared")){f.hide()}}a.show()});l.on("destroy",function(){a.remove()})}return j}return{build:function(F,E){var D=F.name,C=F.element,B=F.elementMode;if(!C||B==CKEDITOR.ELEMENT_MODE_NONE){return}if(B==CKEDITOR.ELEMENT_MODE_REPLACE){C.hide()}var A=F.fire("themeSpace",{space:"top",html:""}).html,z=F.fire("themeSpace",{space:"contents",html:""}).html,y=F.fireOnce("themeSpace",{space:"bottom",html:""}).html,x=z&&F.config.height,w=F.config.tabIndex||F.element.getAttribute("tabindex")||0;if(!z){x="auto"}else{if(!isNaN(x)){x+="px"}}var v="",u=F.config.width;if(u){if(!isNaN(u)){u+="px"}v+="width: "+u+";"}var t=A&&c(F,"top"),s=c(F,"bottom");t&&(t.setHtml(A),A="");s&&(s.setHtml(y),y="");var b="<style>."+F.skinClass+"{visibility:hidden;}</style>";if(d[F.skinClass]){b=""}else{d[F.skinClass]=1}var a=CKEDITOR.dom.element.createFromHtml(['<span id="cke_',D,'" class="',F.skinClass," ",F.id," cke_editor_",D,'" dir="',F.lang.dir,'" title="',CKEDITOR.env.gecko?" ":"",'" lang="',F.langCode,'"'+(CKEDITOR.env.webkit?' tabindex="'+w+'"':"")+' role="application" aria-labelledby="cke_',D,'_arialbl"'+(v?' style="'+v+'"':"")+'><span id="cke_',D,'_arialbl" class="cke_voice_label">'+F.lang.editor+'</span><span class="',CKEDITOR.env.cssClass,'" role="presentation"><span class="cke_wrapper cke_',F.lang.dir,'" role="presentation"><table class="cke_editor" border="0" cellspacing="0" cellpadding="0" role="presentation"><tbody><tr',A?"":' style="display:none"',' role="presentation"><td id="cke_top_',D,'" class="cke_top" role="presentation">',A,"</td></tr><tr",z?"":' style="display:none"',' role="presentation"><td id="cke_contents_',D,'" class="cke_contents" style="height:',x,'" role="presentation">',z,"</td></tr><tr",y?"":' style="display:none"',' role="presentation"><td id="cke_bottom_',D,'" class="cke_bottom" role="presentation">',y,"</td></tr></tbody></table>"+b+"</span></span></span>"].join(""));a.getChild([1,0,0,0,0]).unselectable();a.getChild([1,0,0,0,2]).unselectable();if(B==CKEDITOR.ELEMENT_MODE_REPLACE){a.insertAfter(C)}else{C.append(a)}F.container=a;a.disableContextMenu();F.on("contentDirChanged",function(g){var f=(F.lang.dir!=g.data?"add":"remove")+"Class";a.getChild(1)[f]("cke_mixed_dir_content");var e=this.sharedSpaces&&this.sharedSpaces[this.config.toolbarLocation];e&&e.getParent().getParent()[f]("cke_mixed_dir_content")});F.fireOnce("themeLoaded");F.fireOnce("uiReady")},buildDialog:function(l){var k=CKEDITOR.tools.getNextNumber(),j=CKEDITOR.dom.element.createFromHtml(['<div class="',l.id,"_dialog cke_editor_",l.name.replace(".","\\."),"_dialog cke_skin_",l.skinName,'" dir="',l.lang.dir,'" lang="',l.langCode,'" role="dialog" aria-labelledby="%title#"><table class="cke_dialog'," "+CKEDITOR.env.cssClass," cke_",l.lang.dir,'" style="position:absolute" role="presentation"><tr><td role="presentation"><div class="%body" role="presentation"><div id="%title#" class="%title" role="presentation"></div><a id="%close_button#" class="%close_button" href="javascript:void(0)" title="'+l.lang.common.close+'" role="button"><span class="cke_label">X</span></a><div id="%tabs#" class="%tabs" role="tablist"></div><table class="%contents" role="presentation"><tr><td id="%contents#" class="%contents" role="presentation"></td></tr><tr><td id="%footer#" class="%footer" role="presentation"></td></tr></table></div><div id="%tl#" class="%tl"></div><div id="%tc#" class="%tc"></div><div id="%tr#" class="%tr"></div><div id="%ml#" class="%ml"></div><div id="%mr#" class="%mr"></div><div id="%bl#" class="%bl"></div><div id="%bc#" class="%bc"></div><div id="%br#" class="%br"></div></td></tr></table>',CKEDITOR.env.ie?"":"<style>.cke_dialog{visibility:hidden;}</style>","</div>"].join("").replace(/#/g,"_"+k).replace(/%/g,"cke_dialog_")),i=j.getChild([0,0,0,0,0]),b=i.getChild(0),a=i.getChild(1);b.unselectable();a.unselectable();return{element:j,parts:{dialog:j.getChild(0),title:b,close:a,tabs:i.getChild(2),contents:i.getChild([3,0,0,0]),footer:i.getChild([3,0,1,0])}}},destroy:function(f){var b=f.container,a=f.element;if(b){b.clearCustomData();b.remove()}if(a){a.clearCustomData();f.elementMode==CKEDITOR.ELEMENT_MODE_REPLACE&&a.show();delete f.element}}}})());CKEDITOR.editor.prototype.getThemeSpace=function(e){var d="cke_"+e,f=this._[d]||(this._[d]=CKEDITOR.document.getById(d+"_"+this.name));return f};CKEDITOR.editor.prototype.resize=function(j,i,p,o){var n=this.container,m=CKEDITOR.document.getById("cke_contents_"+this.name),l=o?n.getChild(1):n;CKEDITOR.env.webkit&&l.setStyle("display","none");l.setSize("width",j,true);if(CKEDITOR.env.webkit){l.$.offsetWidth;l.setStyle("display","")}var k=p?0:(l.$.offsetHeight||0)-(m.$.clientHeight||0);m.setStyle("height",Math.max(i-k,0)+"px");this.fire("resize")};CKEDITOR.editor.prototype.getResizable=function(){return this.container};