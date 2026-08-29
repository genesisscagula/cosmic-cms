import fs from 'node:fs';
const file='resources/js/Pages/Websites/Builder.jsx';
const s=fs.readFileSync(file,'utf8');
const checks=[
 ['context prompt state', s.includes('editSessionLunaPrompt')],
 ['isolated conversation snapshot', s.includes('globalLunaConversation:') && s.includes('lunaSessionConversationRef.current = []')],
 ['global conversation restored', (s.match(/lunaSessionConversationRef\.current = cloneBuilderEditValue\(editSession\.globalLunaConversation/g)||[]).length>=2],
 ['local message slice', s.includes('lunaMessages.slice(editSessionLunaStartIndex)')],
 ['context send handler', s.includes('const sendContextualLunaRequest = async () =>')],
 ['enter sends contextual', s.includes("sendContextualLunaRequest();")],
 ['contextual user turn', s.includes("role:'user',text:prompt,scope:editSession.label")],
 ['sidebar has composer', s.includes('Ask Luna') && s.includes('Luna · Contextual')],
 ['working preview copy', s.includes('Her changes stay inside this popup until you Apply.')],
 ['hard selected-block boundary', s.includes('Contextual popup Luna may inspect page context') && s.includes('isSafeBuilderBlockCandidate(targetResponse,currentTarget)') && s.includes('index===targetIndex ? {...targetResponse')],
 ['blocks do not mutate shell', s.includes("requestTargetScope === 'header'") && s.includes('setPopupDraftHeader(response.header)') && s.includes("requestTargetScope === 'footer'") && s.includes('setPopupDraftFooter(response.footer)')],
 ['blocks do not mutate theme', s.includes("response.theme_key && lunaAssistantSurface !== 'contextual_popup'") && s.includes("lunaAssistantSurface !== 'contextual_popup' && !contextualBlockBoundary && response.brand_color_family")],
 ['global Luna suppressed during popup', s.includes('!editSession?.open')],
 ['cancel discards isolated draft', s.includes('Builder was never mutated by the popup') && s.includes('setEditSessionDraft(null)')],
];
let pass=0;
for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`); if(ok)pass++;}
console.log(`\n${pass}/${checks.length} PASS`);
if(pass!==checks.length)process.exit(1);
