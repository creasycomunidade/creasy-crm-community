<?php
namespace App\Modules\Inbox\Http\Controllers;

use App\Events\MessageReceived;
use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WebchatPublicController extends Controller
{
    public function script(Request $request, string $key): Response
    {
        $account=$this->account($key); $site=$this->requestSite($request); $this->assertDomainAllowed($account,$site);
        $config=json_encode(['key'=>$key,'frameUrl'=>route('public.webchat.frame',['key'=>$key]),'site'=>$site,'signature'=>$this->signature($account,$site),'position'=>data_get($account->meta_json,'webchat.position','bottom_right')],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $js=<<<JS
(function(){if(window.__respondeskWebchat{$account->id})return;window.__respondeskWebchat{$account->id}=true;var c={$config},k='respondesk:webchat:'+c.key,v=localStorage.getItem(k);if(!v){v=(window.crypto&&crypto.randomUUID)?crypto.randomUUID():String(Date.now())+Math.random().toString(36).slice(2);localStorage.setItem(k,v)}var f=document.createElement('iframe');f.title='Chat de atendimento';f.setAttribute('aria-label','Chat de atendimento');f.src=c.frameUrl+'?site='+encodeURIComponent(c.site)+'&signature='+encodeURIComponent(c.signature)+'&visitor='+encodeURIComponent(v);f.style.cssText='position:fixed;z-index:2147483000;border:0;background:transparent;width:88px;height:88px;bottom:16px;'+(c.position==='bottom_left'?'left:16px;':'right:16px;');document.body.appendChild(f);window.addEventListener('message',function(e){if(e.source!==f.contentWindow||!e.data||e.data.type!=='respondesk:webchat:resize')return;var o=!!e.data.open;f.style.width=o?'min(390px, calc(100vw - 24px))':'88px';f.style.height=o?'min(650px, calc(100vh - 24px))':'88px';f.style.bottom=o?'12px':'16px';if(c.position==='bottom_left')f.style.left=o?'12px':'16px';else f.style.right=o?'12px':'16px'})})();
JS;
        return response($js,200,['Content-Type'=>'application/javascript; charset=UTF-8','Cache-Control'=>'public, max-age=300','Access-Control-Allow-Origin'=>'*']);
    }

    public function frame(Request $request,string $key): Response
    {
        $account=$this->account($key); [$site]=$this->validateEmbed($request,$account); $webchat=$account->meta_json['webchat']??[];
        return response()->view('webchat.frame',['config'=>[
            'title'=>$webchat['title']??$account->display_name,'welcomeMessage'=>$webchat['welcome_message']??'Olá! Como podemos ajudar?',
            'inputPlaceholder'=>$webchat['input_placeholder']??'Digite sua mensagem...','primaryColor'=>$webchat['primary_color']??'#7c3aed',
            'requireName'=>(bool)($webchat['require_name']??false),'requireEmail'=>(bool)($webchat['require_email']??false),
            'site'=>$site,'signature'=>(string)$request->query('signature'),'visitor'=>(string)$request->query('visitor'),
            'sessionUrl'=>route('public.webchat.session',['key'=>$key]),'messagesUrl'=>route('public.webchat.messages',['key'=>$key]),'sendUrl'=>route('public.webchat.send',['key'=>$key]),
        ]]);
    }

    public function session(Request $request,string $key): JsonResponse
    {
        $account=$this->account($key); $this->validateEmbed($request,$account); $webchat=$account->meta_json['webchat']??[];
        $data=$request->validate([
            'visitor_id'=>['required','string','min:12','max:100','regex:/^[a-zA-Z0-9._-]+$/'],
            'name'=>[($webchat['require_name']??false)?'required':'nullable','string','max:128'],
            'email'=>[($webchat['require_email']??false)?'required':'nullable','email','max:191'],
        ]);
        [$conversation,$created]=DB::transaction(function()use($account,$data){
            $contact=Contact::query()->where('workspace_id',$account->workspace_id)->where('custom_fields->webchat_visitor_id',$data['visitor_id'])->where('custom_fields->webchat_channel_account_id',$account->id)->first();
            $name=trim((string)($data['name']??''));
            if(!$contact){
                $parts=preg_split('/\s+/',$name,2)?:[];
                $contact=Contact::create(['workspace_id'=>$account->workspace_id,'email'=>$data['email']??null,'first_name'=>$parts[0]??'Visitante','last_name'=>$parts[1]??'do site','language'=>'pt-BR','source'=>'webchat','last_seen_at'=>now(),'custom_fields'=>['webchat_visitor_id'=>$data['visitor_id'],'webchat_channel_account_id'=>$account->id]]);
            }else{
                $updates=['last_seen_at'=>now()];
                if($name!==''){ $parts=preg_split('/\s+/',$name,2)?:[]; $updates['first_name']=$parts[0]??$contact->first_name; $updates['last_name']=$parts[1]??null; }
                if(!empty($data['email']))$updates['email']=$data['email'];
                $contact->update($updates);
            }
            $conversation=Conversation::query()->where('workspace_id',$account->workspace_id)->where('channel_account_id',$account->id)->where('contact_id',$contact->id)->whereIn('status',['open','pending','snoozed'])->latest('id')->first();
            $created=false;
            if(!$conversation){
                $conversation=Conversation::create(['workspace_id'=>$account->workspace_id,'channel_account_id'=>$account->id,'contact_id'=>$contact->id,'external_thread_id'=>Str::random(64),'status'=>'open','assigned_to'=>data_get($account->meta_json,'ai_chatbot_id')?'bot':'human','last_message_at'=>now()]);
                $created=true; $welcome=trim((string)data_get($account->meta_json,'webchat.welcome_message',''));
                if($welcome!=='')Message::create(['conversation_id'=>$conversation->id,'direction'=>'out','channel'=>'webchat','type'=>'text','body'=>$welcome,'payload'=>['system_message'=>'welcome'],'status'=>'delivered','sent_by'=>'bot','sent_at'=>now()]);
            }
            return[$conversation,$created];
        });
        return response()->json(['session'=>$conversation->external_thread_id,'conversation'=>$conversation->uuid,'created'=>$created,'messages'=>$this->messagesFor($conversation,0)]);
    }

    public function messages(Request $request,string $key): JsonResponse
    {
        $account=$this->account($key); $this->validateEmbed($request,$account);
        $data=$request->validate(['session'=>['required','string','size:64'],'after_id'=>['nullable','integer','min:0']]); $conversation=$this->conversation($account,$data['session']);
        $conversation->messages()->where('direction','out')->whereIn('status',['sent','delivered'])->update(['status'=>'read']);
        return response()->json(['messages'=>$this->messagesFor($conversation,(int)($data['after_id']??0))]);
    }

    public function send(Request $request,string $key): JsonResponse
    {
        $account=$this->account($key); $this->validateEmbed($request,$account);
        $data=$request->validate(['session'=>['required','string','size:64'],'body'=>['required','string','max:4000']]); $conversation=$this->conversation($account,$data['session']);
        $message=DB::transaction(function()use($conversation,$data){
            $message=Message::create(['conversation_id'=>$conversation->id,'direction'=>'in','channel'=>'webchat','type'=>'text','body'=>trim($data['body']),'payload'=>[],'status'=>'delivered','sent_by'=>'human','sent_at'=>now()]);
            $conversation->update(['status'=>'open','last_message_at'=>$message->sent_at,'last_inbound_at'=>$message->sent_at,'unread_count'=>$conversation->unread_count+1]); return$message;
        });
        $message->load('conversation.channelAccount'); MessageReceived::dispatch($message);
        return response()->json(['message'=>$this->messageArray($message)],201);
    }

    private function account(string $key): ChannelAccount { return ChannelAccount::query()->where('channel','webchat')->where('external_account_id',$key)->where('status','active')->firstOrFail(); }
    private function conversation(ChannelAccount $account,string $session): Conversation { return Conversation::query()->where('workspace_id',$account->workspace_id)->where('channel_account_id',$account->id)->where('external_thread_id',$session)->firstOrFail(); }
    private function validateEmbed(Request $request,ChannelAccount $account): array
    {
        $site=strtolower(trim((string)($request->query('site')?:$request->input('site')))); $signature=(string)($request->query('signature')?:$request->input('signature'));
        abort_unless($site!==''&&hash_equals($this->signature($account,$site),$signature),403); $this->assertDomainAllowed($account,$site); return[$site,$signature];
    }
    private function signature(ChannelAccount $account,string $site): string { return hash_hmac('sha256',$account->id.'|'.$site,(string)config('app.key')); }
    private function requestSite(Request $request): string
    {
        $host=strtolower((string)parse_url((string)$request->headers->get('referer',''),PHP_URL_HOST)); return preg_replace('/^www\./','',$host)?:'arquivo-local';
    }
    private function assertDomainAllowed(ChannelAccount $account,string $site): void
    {
        $allowed=array_values(array_filter((array)data_get($account->meta_json,'webchat.allowed_domains',[]))); if($allowed===[])return;
        abort_unless(collect($allowed)->contains(fn($domain)=>$site===$domain||str_ends_with($site,'.'.$domain)),403,'Este domínio não está autorizado a usar o chat.');
    }
    private function messagesFor(Conversation $conversation,int $afterId): array { return $conversation->messages()->where('id','>',$afterId)->orderBy('id')->limit(100)->get()->map(fn(Message $m)=>$this->messageArray($m))->all(); }
    private function messageArray(Message $message): array { return['id'=>$message->id,'direction'=>$message->direction,'body'=>$message->body,'type'=>$message->type,'status'=>$message->status,'sent_at'=>optional($message->sent_at)->toIso8601String()]; }
}