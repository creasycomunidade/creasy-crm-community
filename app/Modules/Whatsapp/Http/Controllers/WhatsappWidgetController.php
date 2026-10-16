<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Modules\Whatsapp\Models\WhatsappWidget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappWidgetController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $widgets = WhatsappWidget::where('workspace_id', $workspaceId)->latest()->get();

        return Inertia::render('Whatsapp/Widget/Index', ['widgets' => $widgets]);
    }

    public function create(): Response
    {
        return Inertia::render('Whatsapp/Widget/Create');
    }

    public function edit(Request $request, WhatsappWidget $widget): Response
    {
        abort_unless($widget->workspace_id === ($request->user()->current_workspace_id ?? $request->user()->workspace_id), 403);

        return Inertia::render('Whatsapp/Widget/Edit', ['widget' => $widget]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $validated = $request->validate([
            'name'              => ['nullable', 'string', 'max:128'],
            'display_phone'     => ['required', 'string', 'max:32'],
            'prefilled_message' => ['nullable', 'string', 'max:512'],
            'greeting_message'  => ['nullable', 'string', 'max:256'],
            'agent_name'        => ['nullable', 'string', 'max:64'],
            'agent_avatar_color'=> ['nullable', 'string', 'max:16'],
            'button_color'      => ['nullable', 'string', 'max:16'],
            'position'          => ['required', 'in:bottom_right,bottom_left'],
            'allowed_domains'   => ['nullable', 'array'],
            'working_hours_json'=> ['nullable', 'array'],
        ]);

        WhatsappWidget::create(array_merge($validated, ['workspace_id' => $workspaceId]));

        return redirect()->route('client.whatsapp.widget.index')->with('success', 'Widget de chat criado.');
    }

    public function update(Request $request, WhatsappWidget $widget): RedirectResponse
    {
        abort_unless($widget->workspace_id === ($request->user()->current_workspace_id ?? $request->user()->workspace_id), 403);

        $validated = $request->validate([
            'name'              => ['nullable', 'string', 'max:128'],
            'display_phone'     => ['required', 'string', 'max:32'],
            'prefilled_message' => ['nullable', 'string', 'max:512'],
            'greeting_message'  => ['nullable', 'string', 'max:256'],
            'agent_name'        => ['nullable', 'string', 'max:64'],
            'agent_avatar_color'=> ['nullable', 'string', 'max:16'],
            'button_color'      => ['nullable', 'string', 'max:16'],
            'position'          => ['required', 'in:bottom_right,bottom_left'],
            'allowed_domains'   => ['nullable', 'array'],
            'working_hours_json'=> ['nullable', 'array'],
        ]);

        $widget->update($validated);

        return back()->with('success', 'Widget atualizado.');
    }

    public function destroy(Request $request, WhatsappWidget $widget): RedirectResponse
    {
        abort_unless($widget->workspace_id === ($request->user()->current_workspace_id ?? $request->user()->workspace_id), 403);
        $widget->delete();

        return back()->with('success', 'Widget excluído.');
    }

    /** Public JS embed endpoint — no auth, served to any website. */
    public function embed(string $key): \Illuminate\Http\Response
    {
        $widget = WhatsappWidget::where('widget_key', $key)->firstOrFail();
        $workspaceLocale = Workspace::whereKey($widget->workspace_id)->value('default_locale')
            ?: config('app.locale', 'pt');
        $language = strtolower(strtok(str_replace('_', '-', (string) $workspaceLocale), '-')) ?: 'pt';
        $copy = $this->widgetCopy($language);
        $htmlLang = $language === 'pt' ? 'pt-BR' : $language;

        // Domain whitelist check
        $allowedDomains = $widget->allowed_domains ?? [];
        $domainCheck = '';
        if (!empty($allowedDomains)) {
            $domainsJson = json_encode(array_values($allowedDomains));
            $domainCheck = <<<JS

  var _allowed = {$domainsJson};
  var _host = (window.location.hostname || '').toLowerCase().replace(/^www\\./, '');
  var _ok = false;
  for (var _i = 0; _i < _allowed.length; _i++) {
    var _d = (_allowed[_i] || '').toLowerCase().replace(/^www\\./, '').replace(/^https?:\\/\\//, '').split('/')[0];
    if (_host === _d || _host.slice(-(_d.length + 1)) === '.' + _d) { _ok = true; break; }
  }
  if (!_ok) return;

JS;
        }

        // Working hours check — generates JS that returns early if outside schedule
        $workingHoursJson = 'null';
        $workingHoursCheck = '';
        if (!empty($widget->working_hours_json)) {
            $whJson = json_encode($widget->working_hours_json);
            $workingHoursJson = $whJson;
            $workingHoursCheck = <<<JS

  var _wh = {$whJson};
  if (_wh && _wh.enabled) {
    var _tz = _wh.timezone || 'UTC';
    var _now;
    try { _now = new Date(new Date().toLocaleString('en-US', { timeZone: _tz })); } catch(e) { _now = new Date(); }
    var _days = ['sun','mon','tue','wed','thu','fri','sat'];
    var _dayKey = _days[_now.getDay()];
    var _sched = (_wh.schedule || {})[_dayKey];
    var _isOpen = false;
    if (_sched && _sched.enabled) {
      var _cur = _now.getHours() * 60 + _now.getMinutes();
      var _oParts = (_sched.open || '00:00').split(':');
      var _cParts = (_sched.close || '23:59').split(':');
      var _open = parseInt(_oParts[0], 10) * 60 + parseInt(_oParts[1], 10);
      var _close = parseInt(_cParts[0], 10) * 60 + parseInt(_cParts[1], 10);
      _isOpen = _cur >= _open && _cur < _close;
    }
    if (!_isOpen) return;
  }

JS;
        }

        $phone    = $widget->display_phone ?? '';
        $msg      = rawurlencode($widget->prefilled_message ?? '');
        $color    = $widget->button_color ?? '#25D366';
        $posRight = $widget->position !== 'bottom_left';
        $posStyle = $posRight ? 'right:20px' : 'left:20px';
        $transformOrigin = $posRight ? 'right' : 'left';
        $greeting = addslashes($widget->greeting_message ?? '');
        $agentName = addslashes($widget->agent_name ?: $copy['agent_name']);
        $regionLabel = addslashes($copy['region_label']);
        $buttonLabel = addslashes($copy['button_label']);
        $greetingLabel = addslashes($copy['greeting_label']);
        $statusText = addslashes($copy['status_text']);
        $closeLabel = addslashes($copy['close_label']);
        $startChat = addslashes($copy['start_chat']);
        $startWhatsapp = addslashes($copy['start_whatsapp']);
        $agentColor = $widget->agent_avatar_color ?? $color;
        $waUrl    = "https://wa.me/{$phone}?text={$msg}";

        // SVG WhatsApp icon (inline, no external requests)
        $svgIcon = '<svg xmlns=\\"http://www.w3.org/2000/svg\\" width=\\"28\\" height=\\"28\\" fill=\\"white\\" viewBox=\\"0 0 24 24\\"><path d=\\"M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z\\"/></svg>';

        $closeIcon = '<svg xmlns=\\"http://www.w3.org/2000/svg\\" width=\\"24\\" height=\\"24\\" fill=\\"white\\" viewBox=\\"0 0 24 24\\"><path d=\\"M18 6L6 18M6 6l12 12\\" stroke=\\"white\\" stroke-width=\\"2.5\\" stroke-linecap=\\"round\\"/></svg>';

        $js = <<<JS
/* WhatsApp Chat Widget | {$phone} */
(function () {
  'use strict';
{$domainCheck}
{$workingHoursCheck}
  if (document.getElementById('_wacw_root')) return;

  // ── Inject styles ────────────────────────────────────────────────────────
  var _style = document.createElement('style');
  _style.id = '_wacw_style';
  _style.textContent = [
    '#_wacw_root{position:fixed;bottom:20px;{$posStyle};z-index:2147483647;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}',
    '#_wacw_btn{display:flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:{$color};cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.28);border:none;outline:none;transition:transform .2s,box-shadow .2s;-webkit-tap-highlight-color:transparent}',
    '#_wacw_btn:hover{transform:scale(1.1);box-shadow:0 6px 20px rgba(0,0,0,.35)}',
    '#_wacw_btn:active{transform:scale(.96)}',
    '#_wacw_pulse{position:absolute;width:56px;height:56px;border-radius:50%;background:{$color};opacity:.5;animation:_wacw_pulse 2s ease-out infinite}',
    '@keyframes _wacw_pulse{0%{transform:scale(1);opacity:.5}100%{transform:scale(1.7);opacity:0}}',
    '#_wacw_badge{position:absolute;top:-3px;right:-3px;width:16px;height:16px;border-radius:50%;background:#ef4444;border:2px solid #fff;display:none}',
    '#_wacw_tooltip{position:absolute;bottom:66px;{$posStyle};background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:280px;overflow:hidden;transform-origin:bottom {$transformOrigin};transform:scale(.85);opacity:0;pointer-events:none;transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .2s}',
    '#_wacw_tooltip.open{transform:scale(1);opacity:1;pointer-events:auto}',
    '#_wacw_tip_head{background:{$color};padding:14px 16px;display:flex;align-items:center;gap:10px}',
    '#_wacw_tip_avatar{width:38px;height:38px;border-radius:50%;background:{$agentColor};flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#fff;text-transform:uppercase}',
    '#_wacw_tip_info{}',
    '#_wacw_tip_name{color:#fff;font-weight:600;font-size:13px;line-height:1.3}',
    '#_wacw_tip_status{color:rgba(255,255,255,.8);font-size:11px;display:flex;align-items:center;gap:4px}',
    '#_wacw_tip_dot{width:7px;height:7px;border-radius:50%;background:#86efac;display:inline-block}',
    '#_wacw_tip_body{padding:14px 16px}',
    '#_wacw_tip_bubble{background:#f3f4f6;border-radius:0 10px 10px 10px;padding:10px 12px;font-size:13px;color:#1f2937;line-height:1.5;margin-bottom:12px;position:relative}',
    '#_wacw_tip_bubble::before{content:"";position:absolute;top:0;left:-7px;border-width:0 8px 8px 0;border-style:solid;border-color:transparent #f3f4f6 transparent transparent}',
    '#_wacw_tip_cta{display:block;text-align:center;background:{$color};color:#fff;font-size:13px;font-weight:600;padding:10px;border-radius:8px;text-decoration:none;transition:opacity .15s}',
    '#_wacw_tip_cta:hover{opacity:.88}',
    '#_wacw_tip_close{position:absolute;top:10px;right:10px;background:rgba(255,255,255,.2);border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;color:#fff;font-size:14px;line-height:1}',
    '@media(max-width:400px){#_wacw_tooltip{width:calc(100vw - 40px)}}'
  ].join('');
  document.head.appendChild(_style);

  // ── Build DOM ────────────────────────────────────────────────────────────
  var root = document.createElement('div');
  root.id = '_wacw_root';
  root.setAttribute('role', 'region');
  root.setAttribute('aria-label', '{$regionLabel}');
  root.setAttribute('lang', '{$htmlLang}');

  var pulse = document.createElement('div');
  pulse.id = '_wacw_pulse';

  var badge = document.createElement('div');
  badge.id = '_wacw_badge';

  var btn = document.createElement('button');
  btn.id = '_wacw_btn';
  btn.setAttribute('aria-label', '{$buttonLabel}');
  btn.setAttribute('aria-expanded', 'false');
  btn.innerHTML = '{$svgIcon}';

  // Tooltip / greeting card
  var hasGreeting = '{$greeting}' !== '';
  var tooltip = document.createElement('div');
  tooltip.id = '_wacw_tooltip';
  tooltip.setAttribute('role', 'dialog');
  tooltip.setAttribute('aria-label', '{$greetingLabel}');
  tooltip.innerHTML = '<div id="_wacw_tip_head">'
    + '<div id="_wacw_tip_avatar">{$agentName[0]}</div>'
    + '<div id="_wacw_tip_info">'
      + '<div id="_wacw_tip_name">{$agentName}</div>'
      + '<div id="_wacw_tip_status"><span id="_wacw_tip_dot"></span>{$statusText}</div>'
    + '</div>'
    + '<button id="_wacw_tip_close" aria-label="{$closeLabel}">&#x2715;</button>'
    + '</div>'
    + (hasGreeting
      ? '<div id="_wacw_tip_body"><div id="_wacw_tip_bubble">{$greeting}</div>'
        + '<a id="_wacw_tip_cta" href="{$waUrl}" target="_blank" rel="noopener noreferrer">{$startChat}</a></div>'
      : '<div id="_wacw_tip_body"><a id="_wacw_tip_cta" href="{$waUrl}" target="_blank" rel="noopener noreferrer">{$startWhatsapp}</a></div>');

  root.appendChild(pulse);
  root.appendChild(badge);
  root.appendChild(tooltip);
  root.appendChild(btn);
  document.body.appendChild(root);

  // ── State ───────────────────────────────────────────────────────────────
  var open = false;

  function showTooltip() {
    open = true;
    tooltip.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
    btn.innerHTML = '{$closeIcon}';
    pulse.style.animationPlayState = 'paused';
    badge.style.display = 'none';
  }

  function hideTooltip() {
    open = false;
    tooltip.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML = '{$svgIcon}';
    pulse.style.animationPlayState = 'running';
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    if (open) { hideTooltip(); } else { showTooltip(); }
  });

  var closeBtn = document.getElementById('_wacw_tip_close');
  if (closeBtn) {
    closeBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      hideTooltip();
    });
  }

  document.addEventListener('click', function (e) {
    if (open && !root.contains(e.target)) hideTooltip();
  });

  document.addEventListener('keydown', function (e) {
    if (open && (e.key === 'Escape' || e.keyCode === 27)) hideTooltip();
  });

  // Auto-show greeting after 3 s if greeting message is set and not dismissed
  if (hasGreeting && !sessionStorage.getItem('_wacw_seen_{$key}')) {
    setTimeout(function () {
      if (!open) {
        showTooltip();
        badge.style.display = 'block';
        sessionStorage.setItem('_wacw_seen_{$key}', '1');
      }
    }, 3000);
  }
})();
JS;

        return response($js, 200, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
    /** Localized system copy for the public widget. */
    private function widgetCopy(string $language): array
    {
        if ($language === 'en') {
            return [
                'agent_name' => 'Support',
                'region_label' => 'WhatsApp Chat',
                'button_label' => 'Chat on WhatsApp',
                'greeting_label' => 'WhatsApp greeting',
                'status_text' => 'Typically replies instantly',
                'close_label' => 'Close',
                'start_chat' => 'Start Chat',
                'start_whatsapp' => 'Start Chat on WhatsApp',
            ];
        }

        return [
            'agent_name' => 'Atendimento',
            'region_label' => 'Atendimento pelo WhatsApp',
            'button_label' => 'Conversar pelo WhatsApp',
            'greeting_label' => 'Mensagem de boas-vindas do WhatsApp',
            'status_text' => 'Normalmente responde na hora',
            'close_label' => 'Fechar',
            'start_chat' => 'Iniciar conversa',
            'start_whatsapp' => 'Iniciar conversa no WhatsApp',
        ];
    }
}
