# Webdesign Decision Matrix Ontology - Research Fill

Snapshot date: 2026-05-24

Scope:

- Only fills the rough areas already present in the user's
  `C:\Users\thaum\dev\wdd.md`.
- Does not fill every `SKELETON.md` TODO.
- Prioritizes English-language professional / official sources.
- Excludes Korean exam-standard hunting for this pass unless the user asks for
  a separate exam-alignment pass.
- Keeps only material that can plausibly help Axismundi's Decision Matrix,
  ontology, TT5 audit, Pilot template pass, or later distributable/release
  work.
- Does not import book body text.
- Intended as supplementary evidence for v3.6.25 Phase 1 diagnostic.

## Covered User Rough Notes

```txt
1. Project concept / structure
   PMBOK, MaRMI-III, UML, WBS

2. Ideation / concept visualization / prototype fidelity
   SCAMPER, Hyperbolic Tree question, CodePen question, fidelity

3. Usability testing
   formative testing, Nielsen heuristics

4. Information architecture / wireframe / storyboard
   content structure, IA types, navigation, layout, storyboard

5. Aesthetic / usability design
   design process, Gestalt, grid/responsive, UX/UI, Honeycomb

6. Webdesign process / implementation notes
   5-step and 3-step process, client/server, screen/functional elements

7. Project close / deliverables
   SWEBOK, content/data preservation, CBD deliverables, final report
```

---

## 1. Project Concept / Structure

Section status:

```txt
detail_pass: 1
scope: user's rough notes only
source_snapshot: 2026-05-24
review_unit: Section 1 only
copyright_classification:
  user notes = user-authored digest
  external sources = linked professional / official references, paraphrased
  book body text = not imported
```

Sources:

- [PMBOK Guide, PMI](https://www.pmi.org/pmbok-guide-standards/foundational/pmbok)
- [PMBOK 7th Edition FAQ, PMI PDF](https://www.pmi.org/-/media/pmi/documents/public/pdf/pmbok-standards/pmbok-guide-public-faqs-1-july-2021.pdf?v=18e3382e-0c61-4b86-b0e8-daaf5ea2b13d)
- [ISO/IEC/IEEE 12207:2017, ISO](https://www.iso.org/standard/63712.html)
- [Introduction to OMG Specifications, Object Management Group](https://www.omg.org/gettingstarted/specintro.htm)
- [ISO 9241-210:2019, ISO](https://www.iso.org/standard/77520.html)

User note coverage:

```txt
PMBOK:
  Covered user notes:
    5 process groups:
      initiating / planning / executing / monitoring and controlling / closing
    10 knowledge areas:
      integration / scope / schedule / cost / quality / human resource /
      communication / risk / procurement / stakeholder
    webdesign process:
      project planning -> website planning -> site build/design development ->
      testing/debugging -> deploy/promotion/maintenance
    outcome:
      output / artifact
    related documents:
      RFP / proposal / project plan / final report

MaRMI-III / CBD:
  Covered user notes:
    component-based development
    4 processes and 30 activities
    deliverable guidance for stakeholder communication
    based on ISO/IEC 12207 development process
    layered development process
    UML-based object-oriented modeling

UML:
  Covered user notes:
    class diagram / sequence diagram / use case diagram / state diagram /
    activity diagram

WBS:
  Covered user notes:
    deliverable at top level
    tree-structured diagram
    project goal / major phases / work packages / assignment
    decompose work, define order/dependencies, estimate duration, allocate
    resources, visualize schedule with Gantt-like tool
```

Research fill:

```txt
A. PMBOK as project vocabulary, not a new process mandate:
  The user's 5 process groups and 10 knowledge areas are the older
  process/knowledge-area vocabulary. PMI's current PMBOK page emphasizes newer
  editions and performance-domain/principle-oriented framing.

  Axismundi guardrail:
    Preserve the user's process vocabulary because it maps well to Axismundi
    phase discipline, but do not reopen Axismundi's Lock 1-5 process model or
    replace it with PMBOK.

B. PMBOK -> Axismundi phase mapping:
  initiating:
    cycle trigger, user GO, entry fence, current standing state

  planning:
    Phase 0 plan, scope, source inputs, non-goals, risks

  executing:
    Phase 2 implementation or documentation drafting

  monitoring and controlling:
    Phase 1 diagnostic, Phase 3 verification, reviewer verdicts, amend cycles

  closing:
    Phase 5 close, commit/push, handoff meta-docs, memory promotion

  Outcome / output / artifact:
    Use "output" for produced file/result, "artifact" for durable evidence that
    must remain traceable.

C. PMBOK documents -> Axismundi artifacts:
  RFP:
    external/request source. In Axismundi, this is usually the user trigger or
    source brief.

  proposal:
    proposed route or Phase 0 candidate plan.

  project plan:
    Phase 0 plan plus expected route.

  final report:
    Phase 5 close and, later, portfolio/release report.

D. MaRMI-III / CBD:
  User's MaRMI/CBD note is useful for deliverable and component thinking, but
  English professional source verification is incomplete in this pass.

  ISO/IEC/IEEE 12207 gives a software life-cycle process reference, but it does
  not force a single lifecycle model, method, or modeling approach.

  Axismundi guardrail:
    Treat MaRMI/CBD as user-note evidence for component/deliverable vocabulary.
    Do not promote it to core ontology until a source-alignment pass verifies
    the specific methodology source.

E. UML:
  OMG identifies UML as a methodology-independent modeling specification and
  includes class, use case, sequence, statechart/state, and activity diagrams
  among its common diagram types.

  Axismundi mapping:
    class diagram:
      useful only if a plugin/runtime object model needs explicit structure

    sequence diagram:
      useful for interactions such as theme switcher, editor bridge, or future
      plugin flows

    use case diagram:
      useful for stakeholder/user goal framing, especially before Pilot template
      or plugin work

    state diagram:
      useful for explicit state contracts such as data-theme auto/light/dark

    activity diagram:
      useful for workflow routing, publish tooling, validation, and release
      process documentation

  Guardrail:
    UML is optional modeling support, not a required artifact for every cycle.

F. WBS:
  WBS is a deliverable/work decomposition lens. It can help split large work
  into bounded cycles and prevent scope soup.

  Axismundi mapping:
    project goal:
      G1/G2 ultimate goal or cycle objective

    major phases:
      Phase 0/1/2/3/5 or a multi-cycle route

    work packages:
      bounded implementation/doc/research units

    assignment:
      Codex execution / Opus review / user decision split

    dependencies:
      source inputs, memory guardrails, validation gates, user GO prerequisites

  Guardrail:
    WBS should create smaller work units, not justify expanding one cycle to
    contain the whole roadmap.
```

Decision Matrix addition:

```txt
source_section: PART 01 CH01 SECTION 02
workflow_stage: concept
decision_point: choose project-management vocabulary
matrix_fields_to_test:
  source_page_reference
  source_confidence
  copyright_classification
  methodology_source
  legacy_or_current
  Axismundi_phase_mapping
  process_group
  knowledge_area
  output_artifact
  modeling_artifact
  work_package
  dependency

candidate_rows:
  Project-process vocabulary decision:
    input: PMBOK legacy/current framing + Axismundi Lock 1-5
    output: mapped vocabulary, no replacement of Axismundi process
    Axismundi_route: core_ontology_now

  Document artifact decision:
    input: RFP / proposal / project plan / final report terms
    output: Axismundi artifact mapping
    Axismundi_route: core_ontology_now, phase5_later

  Modeling notation decision:
    input: UML diagram vocabulary + actual modeling need
    output: optional diagram type or "none needed"
    Axismundi_route: TT5_audit_later, Pilot_harness_later,
      plugin_or_interaction_later

  Work decomposition decision:
    input: G1/G2 objective + dependencies + validation gates
    output: WBS-like bounded cycle/work-package split
    Axismundi_route: M14_application
```

Axismundi application:

```txt
Recommended future order:
  1. Section 1 defines project/process/work-package vocabulary.
  2. Section 4-5 define page/design decision lenses.
  3. Section 7 defines deliverable and handoff artifact boundaries.
  4. v3.6.25 Phase 1 reconciles these into a Decision Matrix schema.
  5. TT5 / Google Sites / Pilot work uses the schema rather than adding a new
     project methodology on the fly.

Section 4-5-7 cross-link:
  Section 1 WBS helps split Section 4 storyboards and Section 5 UX/layout
  decisions into bounded work packages.
  Section 1 PMBOK closing vocabulary maps to Section 7 final reports and
  handoff docs.
  Section 1 UML state/sequence/activity diagrams are optional tools for future
  interactions or workflows, not required by default.

Anti-collapse rules:
  PMBOK vocabulary does not replace Axismundi Lock 1-5 phase discipline.
  MaRMI/CBD remains user-note evidence until source-aligned.
  UML diagrams are optional modeling aids, not mandatory deliverables.
  WBS is a scope-control tool, not a reason to expand a cycle.
```

Opus review checklist:

```txt
1. User-note coverage:
   Verify PMBOK 5 process groups / 10 knowledge areas / documents, MaRMI-III,
   UML diagram list, and WBS elements are preserved.

2. Source validity:
   Confirm source roles:
     PMI = PMBOK official framing
     PMI FAQ = PMBOK 7 current/legacy transition support
     ISO/IEC/IEEE 12207 = lifecycle process reference
     OMG = UML official specification authority
     ISO 9241-210 = human-centered design context

3. Axismundi relevance:
   Confirm Section 1 supports Decision Matrix ontology, cycle/work-package
   splitting, TT5/Pilot/Google Sites future work, and Phase 5 handoff language.

4. Anti-collapse guardrail:
   Confirm PMBOK/MaRMI/UML/WBS are vocabulary and modeling aids, not replacement
   methodology or mandatory artifact lists.

5. Matrix usefulness:
   Confirm fields can reconcile with Sections 4/5/7 and help Phase 1 schema
   testing without overfitting to project-management theory.
```

---

## 2. Ideation / Concept Visualization / Prototype Fidelity

Sources:

- [Human Centered Design, NIST](https://www.nist.gov/itl/iad/visualization-and-usability-group/human-factors-human-centered-design)
- [ISO 9241-210:2019, ISO](https://www.iso.org/standard/77520.html)

Research fill:

```txt
SCAMPER:
  Treat as ideation-method vocabulary, not Axismundi product rule.
  Useful matrix fields: ideation_method, divergence_or_convergence,
  output_artifact.

concept visualization:
  sketching / diagramming / storyboarding / prototype / infographic are
  visualization modes with different fidelity and evidence value.

fidelity:
  Low fidelity = cheap structure / idea validation.
  Mid fidelity = flow and major UI relationships.
  High fidelity = visual detail + interaction expectations, but still not
  runtime proof unless implemented in browser/Pilot.
```

User open questions:

```txt
Hyperbolic Tree:
  Route as decisions_candidate, not default implementation.
  Use only if the prototype index truly needs dense non-linear relationship
  browsing. Otherwise a simpler IA map / sitemap is lower-risk.

CodePen:
  Good for isolated public demos, but weaker for Axismundi because repo-local
  lineage, local assets, validation scripts, and WordPress/Pilot context matter.
  Default recommendation: local coded prototype in repo unless a throwaway
  external embed is explicitly desired.
```

Decision Matrix addition:

```txt
source_section: PART 01 CH02 SECTION 02
workflow_stage: concept / prototype
decision_point: choose visualization and fidelity mode
matrix_fields_to_test:
  visualization_mode
  fidelity_level
  public_or_repo_local
  implementation_risk
  decision_candidate
```

---

## 3. Usability Testing

Sources:

- [10 Usability Heuristics, Nielsen Norman Group](https://www.nngroup.com/articles/ten-usability-heuristics/)
- [ISO 9241-210:2019, ISO](https://www.iso.org/standard/77520.html)
- [WCAG 2.2, W3C](https://www.w3.org/TR/WCAG22/)

Research fill:

```txt
formative usability testing:
  Use during design/build to find problems and improve the interface.
  User's metrics map well to Axismundi:
    information efficiency
    task efficiency
    satisfaction
    intuitiveness
    accessibility
    responsiveness

heuristic evaluation:
  Expert review against heuristics.
  Nielsen's 10 heuristics should be referenced as evaluation criteria, not as
  final proof of usability.

accessibility:
  WCAG remains a separate normative gate. Heuristics can flag likely problems,
  but WCAG validation needs explicit criteria.
```

Decision Matrix addition:

```txt
source_section: PART 02 CH02 SECTION 02
workflow_stage: usability
decision_point: choose evaluation method and gate
matrix_fields_to_test:
  evaluation_type
  evaluator
  timing
  metric
  normative_or_heuristic
```

Axismundi application:

```txt
Phase 3 browser smoke = technical/runtime evidence.
Human visual QA = qualitative usability/visual evidence.
Validator suite = regression evidence.
These should remain separate evidence types in the matrix.
```

---

## 4. Information Architecture / Wireframe / Storyboard

Section status:

```txt
detail_pass: 1
scope: user's rough notes only
source_snapshot: 2026-05-24
review_unit: Section 4 only
copyright_classification:
  user notes = user-authored digest
  external sources = linked professional / official references, paraphrased
  book body text = not imported
```

Sources:

- [IA vs. Navigation, Nielsen Norman Group](https://www.nngroup.com/articles/ia-vs-navigation/)
- [Wireflows, Nielsen Norman Group](https://www.nngroup.com/articles/wireflows/)
- [Storyboards Help Visualize UX Ideas, Nielsen Norman Group](https://www.nngroup.com/articles/storyboards-visualize-ideas/)
- [Theme templates and template parts, WordPress Developer Handbook](https://developer.wordpress.org/themes/block-themes/templates-and-template-parts/)
- [Introduction to Templates, WordPress Developer Handbook](https://developer.wordpress.org/themes/templates/introduction-to-templates/)
- [Templates, WordPress Developer Handbook](https://developer.wordpress.org/themes/templates/templates/)
- [Patterns, WordPress Developer Handbook](https://developer.wordpress.org/themes/patterns/)

User note coverage:

```txt
IA:
  Covered user notes:
    facts / concepts / procedures / processes / principles
    website IA, navigation structure, tool operation
    registration / login / auth / password reset / profile / search / nav menu
    content management / feedback / notifications
    content collection -> grouping -> structuring -> hierarchy -> structure test
    clear classification traits: alphabet / date / location
    unclear classification traits: subject / function / user / symbol
    classification / structure / labeling / navigation
    hierarchy / depth / width / level
    hierarchical / hub-and-spoke / nested-doll / dashboard / labeling

navigation:
  Covered user notes:
    nav bar / menu / link / image map / sitemap / sidebar / footer nav
    visitor and location information
    sticky header / dropdown / hamburger / mega menu
    sequence / grid / hierarchy / network

wireframe/layout:
  Covered user notes:
    wireframe = early idea and structure expression
    layout = wireframe-based placement of header / nav / content / footer / banner
    header / navigation / contents / aside / footer / ads
    content priority, simplicity, consistency, user test, template consistency
    fixed-width / fluid / responsive / adaptive

storyboard:
  Covered user notes:
    work instruction and whole-site structure document
    screen plan / layout / navigation / functions with drawing + explanation
    wireframe + scenario
    cover / revision history / screen design / service flow / page details
    design elements / functions / requirements
    visible major elements before visual detail
    persona -> goal/need -> scenario -> visualization -> feedback improvement
    page name / font / font size / color / image / text / link / asset notes
```

Research fill:

```txt
A. IA and navigation must stay separate:
  NN/g separates IA from navigation: IA is the underlying organization,
  relationships, nomenclature, and content/function inventory; navigation is
  the UI surface that helps people move through that structure.

  Axismundi rule:
    Do not choose a navigation widget because it looks good before the IA is
    known. A sticky header, mega menu, sidebar, footer nav, or Navigation block
    is an implementation/presentation choice, not the IA itself.

  Matrix implication:
    IA decision fields and navigation-pattern fields must be separate.

B. IA structures -> Axismundi mapping:
  hierarchical:
    Default posture for page/template/category relationships.
    Good for Pilot templates and later distributable structure.

  hub-and-spoke:
    Good for landing pages, catalog hubs, documentation hubs, and guided
    exploration pages.

  nested-doll:
    Good for stepwise onboarding, process explanation, or "drill down" story
    pages. Use carefully; it can hide sibling choices.

  dashboard:
    Good for status/overview/admin-like surfaces, not default public marketing
    pages unless the page truly summarizes many live states.

  labeling:
    Naming/taxonomy layer. Must be checked against user vocabulary, content
    inventory, and WordPress slugs. Do not let labels drift between catalog,
    Pilot, and future distributable.

  sequence:
    Good for tutorials, workflows, prototype walkthroughs, and release steps.

  grid:
    Good for block catalogs, card lists, specimen walls, media galleries, and
    comparison surfaces.

  network:
    Good for exploratory knowledge maps. Higher risk for default theme pages
    because it can weaken findability and implementation simplicity.

C. Wireframe vs layout:
  wireframe:
    Decides content priority, page regions, IA exposure, major interactions,
    and what must be visible. It should remain low-to-mid fidelity enough to
    change without sunk-cost pressure.

  layout:
    Decides spatial composition, rhythm, grid, responsive behavior, and the
    placement of header/nav/main/aside/footer/ads-like surfaces.

  fixed-width:
    Avoid for Pilot/distributable pages except constrained specimens or embeds.

  fluid:
    Useful for flexible regions, but must have readability bounds.

  responsive:
    Default Axismundi posture for theme and Pilot work.

  adaptive:
    Useful only when distinct breakpoint-specific layouts are worth the
    maintenance cost.

D. Storyboard as implementation handoff:
  UX storyboards explain a user, scenario, motivation, and change over time.
  Web production storyboards specify pages/screens, layout, navigation,
  functions, assets, and acceptance checks.

  Axismundi minimal storyboard fields:
    page_or_template_id
    user_goal
    entry_path
    content_priority
    IA_type
    navigation_type
    layout_type
    WordPress_surface
    block_or_pattern_sources
    responsive_notes
    accessibility_notes
    assets_required
    acceptance_gates
    open_decisions

E. WordPress implementation mapping:
  templates:
    Structural files for page/post/archive/search/404/front-page/home-like
    outputs. They decide where block markup, template parts, and patterns appear.

  template parts:
    Reusable site structure such as header and footer. They should map to
    repeated storyboard regions, not one-off page content.

  patterns:
    Reusable block groups/sections. They are good targets for repeated page
    components discovered during wireframe/storyboard work.

  Navigation block / menu surface:
    Implementation of a navigation decision. It does not define IA by itself.

  Guardrail:
    WordPress surfaces are implementation destinations, not source authority for
    the IA. TT5, Google Sites, and Pilot can inform decisions later, but Section
    4 creates the evaluation frame first.
```

Decision Matrix addition:

```txt
source_section: PART 03 CH01
workflow_stage: IA / wireframe / storyboard
decision_point: choose page structure and handoff artifact
matrix_fields_to_test:
  source_page_reference
  source_confidence
  copyright_classification
  page_or_template
  IA_type
  navigation_type
  layout_type
  WordPress_surface
  reusable_surface
  asset_dependency
  responsive_strategy
  acceptance_gate

candidate_rows:
  IA classification decision:
    input: content inventory + user goals + section taxonomy
    output: hierarchy / hub / sequence / grid / network / dashboard route
    Axismundi_route: TT5_audit_later, Pilot_harness_later

  Navigation pattern decision:
    input: IA route + depth/width/level + priority paths
    output: sticky header / dropdown / hamburger / mega menu / sidebar / footer
    Axismundi_route: Pilot_harness_later

  Wireframe/layout decision:
    input: page goal + content priority + responsive constraints
    output: fixed / fluid / responsive / adaptive layout posture
    Axismundi_route: Pilot_harness_later, distributable_later

  Storyboard handoff decision:
    input: IA + wireframe + scenario + asset needs
    output: page-by-page implementation instruction
    Axismundi_route: TT5_audit_later, Google_Sites_extraction_later,
      Pilot_harness_later
```

Axismundi application:

```txt
Recommended future order:
  1. Decision Matrix ontology defines Section 4 fields.
  2. TT5 audit checks how a mature block theme expresses templates, parts,
     patterns, navigation, and responsive layout.
  3. Google Sites extraction is evaluated through the same IA/wireframe/storyboard
     frame, not by visual taste alone.
  4. Pilot template pass creates page storyboards before editing templates.
  5. Distributable skeleton decides separately what Pilot findings can graduate.

Do not jump from TT5/Google Sites reference directly to template markup.
Do not treat Pilot template additions as distributable inheritance.
Do not let the Navigation block, header part, or visual menu style collapse IA,
navigation, layout, and implementation into one decision.
```

Opus review checklist:

```txt
1. User-note coverage:
   Verify that the user's IA / navigation / wireframe / layout / storyboard
   notes are preserved without importing book body text.

2. Source validity:
   Confirm the English professional/official source roles:
     NN/g = IA/navigation/wireflow/storyboard professional UX frame
     WordPress Developer Handbook = implementation surface mapping

3. Axismundi relevance:
   Confirm that Section 4 supports TT5 audit, Google Sites extraction, Pilot
   template pass, and later distributable work without implementing any of them.

4. Anti-collapse guardrail:
   Confirm that IA, navigation, wireframe/layout, storyboard, and WordPress
   implementation are separated.

5. Matrix usefulness:
   Confirm that the proposed fields are useful for v3.6.25 Phase 1 schema
   testing and do not overfit to this one section.
```

---

## 5. Aesthetic / Usability Design

Section status:

```txt
detail_pass: 1
scope: user's rough notes only
source_snapshot: 2026-05-24
review_unit: Section 5 only
copyright_classification:
  user notes = user-authored digest
  external sources = linked professional / official references, paraphrased
  book body text = not imported
```

Sources:

- [Gestalt Principles, Interaction Design Foundation](https://ixdf.org/literature/topics/gestalt-principles)
- [User Experience Honeycomb, Peter Morville](https://intertwingled.org/user-experience-honeycomb/)
- [10 Usability Heuristics, Nielsen Norman Group](https://www.nngroup.com/articles/ten-usability-heuristics/)
- [Multi-Device Layout Patterns, Luke Wroblewski](https://www.lukew.com/ff/entry.asp?1514)
- [Media query fundamentals, MDN](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Media_queries)
- [Responsive images, web.dev](https://web.dev/learn/design/responsive-images)
- [Responsive UI, Material Design](https://m1.material.io/layout/responsive-ui.html)
- [WCAG 2.2, W3C](https://www.w3.org/TR/WCAG22/)

User note coverage:

```txt
design problem solving:
  Covered user notes:
    plan -> research -> analysis -> synthesis -> evaluation

website design elements:
  Covered user notes:
    layout / color / typography / image and graphics / navigation
    responsive design / metaphor / accessibility / personalized experience
    load speed

Gestalt:
  Covered user notes:
    proximity / similarity / continuity / closure / symmetry
    figure-ground / common fate

grid and responsive:
  Covered user notes:
    container / column / gutter / margin / module
    thirds / golden ratio / column / baseline / horizontal-vertical grid
    responsive layout / responsive grid / viewport / media query
    flexible image and media / responsive typography / loading-speed optimization
    Luke Wroblewski patterns:
      Mostly Fluid / Column Drop / Layout Shifter / Tiny Tweaks / Off Canvas

UX / UI:
  Covered user notes:
    task analysis / user research and analysis / IA / wireframe / prototype
    usability test / feedback / final implementation and launch / continuous improvement
    User Journey Map / Experience Map
    persona / timeline / touch points / channels / emotional states
    pain points / insights
    interaction design / responsive design / user-centred design
    visual design / ethical design
    press / long press / scroll / drag / pull to refresh
    single tap / double tap / pinch
    Honeycomb:
      useful / usable / credible / findable / accessible / desirable / valuable
    Nielsen heuristics 10-item set
    UI grouping benefits:
      speed / reusability / consistency / readability / maintainability
    terms:
      fidelity / affordance / Agile UX / brand identity / breadcrumb
      Decision Matrix / user flow / mockup / dark pattern / color palette
      hamburger button / whitespace / GUI
```

Research fill:

```txt
A. Design problem solving:
  User's design genesis ladder:
    imitation -> modification -> adaptation -> innovation

  Axismundi use:
    Treat this as a concept-maturity lens. It can describe whether a candidate
    pattern is copied, locally adjusted, adapted to Axismundi constraints, or
    genuinely promoted into an Axismundi rule. It is not a license to copy TT5,
    Google Sites, or any external layout by appearance.

  User's extended design process:
    initiation -> confirmation -> research -> analysis -> synthesis ->
    evaluation -> development -> delivery

  Axismundi use:
    Treat this as the longer handoff chain around the shorter problem-solving
    loop. It helps route artifacts from early intent through delivery, while the
    5-step problem-solving loop below remains the compact cycle lens.

  User's plan -> research -> analysis -> synthesis -> evaluation loop should be
  preserved as a reusable workflow lens, not a visual-style prescription.

  Axismundi mapping:
    plan = Phase 0 plan / entry fence
    research = Phase 1 diagnostic
    analysis = route recommendation + matrix classification
    synthesis = Phase 2 implementation plan
    evaluation = Phase 3 verification + human QA when relevant

B. Gestalt as visual grouping rules:
  Use Gestalt principles as review criteria for grouping and hierarchy:
    proximity = related items should be spatially close
    similarity = repeated roles should share visual treatment
    continuity = reading path and visual flow should not fight page goals
    closure = partial visual systems can imply a whole, but avoid ambiguity
    symmetry/order = use stable alignment where it clarifies structure
    figure-ground = foreground content must be distinguishable from background
    common fate = elements moving/changing together imply relationship

  Axismundi guardrail:
    Gestalt is an evaluation lens, not permission to add decoration. It should
    support Section 4 IA/storyboard decisions.

C. Grid and responsive layout:
  Grid vocabulary:
    container / column / gutter / margin / module are layout-system primitives.
    Material's grid guidance is useful as a known reference, but Axismundi
    should map it to local CSS tokens and WordPress block constraints.

  Luke Wroblewski responsive pattern mapping:
    Mostly Fluid:
      Good default for content-heavy theme pages. Works well with constrained
      main content and wider margins on large screens.

    Column Drop:
      Good when secondary columns can stack below or above primary content.
      Useful for archive/sidebar-like pages.

    Layout Shifter:
      Higher design and CSS cost. Use only when page purpose changes materially
      across breakpoints.

    Tiny Tweaks:
      Good for simple pages with one main column and minor type/spacing changes.

    Off Canvas:
      Useful for navigation/tools only when hidden content remains accessible
      and discoverable. Higher risk for default theme pages.

  Responsive implementation lens:
    MDN/web.dev make responsive design a viewport/media/content-size problem,
    not a fixed-device checklist. Breakpoints should follow content and layout
    stress points, not arbitrary device names.

  Media/performance lens:
    Responsive images and media must consider source selection, intrinsic
    dimensions, lazy loading, and layout stability. Visual design decisions can
    create performance debt.

D. UX dimensions and heuristics:
  Honeycomb dimensions:
    useful / usable / desirable / findable / accessible / credible / valuable
    can become matrix evaluation dimensions for prototypes, Pilot templates,
    and later distributable pages.

  Nielsen heuristics:
    Treat as heuristic review, not normative proof. They are useful for:
      system status
      match with real-world language
      user control
      consistency
      error prevention
      recognition over recall
      flexibility
      aesthetic/minimalist design
      error recovery
      help/documentation

  WCAG:
    Accessibility cannot be satisfied by "accessibility" as a design value.
    WCAG remains the normative reference for testable accessibility criteria.

E. UX journey / experience map:
  User Journey Map / Experience Map fields should support template decisions:
    persona
    timeline
    touch points
    channels
    emotional states
    pain points
    insights

  Axismundi rule:
    Journey maps can inform storyboards and page templates, but they do not
    directly authorize Pilot markup. They need Section 4 handoff fields first.

F. UI gestures:
  Gestures are interaction assumptions. For web/theme work, every gesture-like
  interaction must have pointer, keyboard, and accessibility fallback.

  Pilot/distributable rule:
    Press, long press, drag, pull-to-refresh, double tap, and pinch should not
    become core theme requirements without a separate interaction cycle.

G. UI grouping benefits:
  User's grouping benefits map cleanly to Axismundi review terms:
    speed = scanning efficiency
    reusability = pattern/template reuse
    consistency = design-system and WordPress surface consistency
    readability = type/spacing/content hierarchy
    maintainability = CSS and pattern governance
```

Decision Matrix addition:

```txt
source_section: PART 03 CH02
workflow_stage: visual_design / responsive / UX_design
decision_point: choose visual grouping, responsive pattern, and UX review lens
matrix_fields_to_test:
  source_page_reference
  source_confidence
  copyright_classification
  design_principle
  grouping_rule
  layout_grid_primitives
  responsive_pattern
  breakpoint_basis
  media_performance_risk
  UX_dimension
  heuristic_gate
  accessibility_gate
  interaction_assumption
  token_or_layout_dependency

candidate_rows:
  Gestalt grouping decision:
    input: Section 4 IA/storyboard + visual grouping need
    output: proximity / similarity / continuity / closure / figure-ground /
      symmetry / common-fate rule
    Axismundi_route: Pilot_harness_later

  Responsive layout decision:
    input: wireframe layout + content stress point + viewport behavior
    output: Mostly Fluid / Column Drop / Layout Shifter / Tiny Tweaks /
      Off Canvas / custom local pattern
    Axismundi_route: TT5_audit_later, Pilot_harness_later

  UX review decision:
    input: prototype/template candidate
    output: Honeycomb dimensions + Nielsen heuristic review + WCAG gate
    Axismundi_route: Pilot_harness_later, human_QA_later

  Gesture/interaction decision:
    input: interaction need + device assumptions
    output: pointer/keyboard/accessibility fallback requirement
    Axismundi_route: separate_interaction_cycle_later
```

Axismundi application:

```txt
Recommended future order:
  1. Section 4 decides IA/wireframe/storyboard.
  2. Section 5 decides grouping, grid, responsive pattern, and UX review lens.
  3. TT5 audit checks which design/layout patterns a mature block theme uses.
  4. Google Sites extraction is filtered through Section 4 + Section 5, not copied.
  5. Pilot template pass applies only bounded, reviewed patterns.

Section 4 cross-link:
  Gestalt -> supports IA grouping and storyboard visual hierarchy.
  Grid/responsive -> implements wireframe/layout decisions.
  Honeycomb -> evaluates whether page goals remain useful/findable/credible/etc.
  Nielsen -> heuristic review of template/prototype behavior.
  WCAG -> accessibility gate, separate from aesthetic preference.

Do not turn Gestalt, Honeycomb, or Material/Luke responsive patterns into a new
visual style doctrine. They are evaluation and decision lenses.
Do not let "responsive" mean arbitrary breakpoints; content and layout stress
points should drive breakpoints.
Do not make touch gestures mandatory for core theme behavior without keyboard
and accessibility alternatives.
```

Opus review checklist:

```txt
1. User-note coverage:
   Verify design elements / Gestalt / grid-responsive / UX process / journey map /
   Honeycomb / Nielsen heuristics / UI grouping / terminology coverage.

2. Source validity:
   Confirm English professional/official source roles:
     IxDF = Gestalt overview
     Morville = Honeycomb
     NN/g = heuristic review
     LukeW = responsive layout patterns
     MDN/web.dev = responsive implementation mechanics
     Material = grid reference
     W3C WCAG = accessibility normative gate

3. Axismundi relevance:
   Confirm Section 5 supports TT5 audit, Google Sites extraction, Pilot template
   pass, and later human QA without implementing any of them.

4. Anti-collapse guardrail:
   Confirm design principles, responsive implementation, UX evaluation, WCAG,
   and Pilot template implementation remain separate.

5. Matrix usefulness:
   Confirm fields are useful for v3.6.25 Phase 1 schema testing and pair cleanly
   with Section 4 fields.
```

---

## 6. Webdesign Process / Implementation Notes

Sources:

- [HTML for web developers, WHATWG](https://html.spec.whatwg.org/dev/)
- [HTML, MDN](https://developer.mozilla.org/en-US/docs/Web/HTML)
- [Introduction to server-side programming, MDN](https://developer.mozilla.org/en-US/docs/Learn_web_development/Extensions/Server-side/First_steps/Introduction)
- [Theme.json Reference, WordPress Developer Handbook](https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/)
- [Interactivity API Reference, WordPress Developer Handbook](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/)

Research fill:

```txt
5-step process:
  planning -> site planning -> design/development -> testing/debugging ->
  deploy/promote/maintain maps cleanly to Axismundi phase cycles.

3-step process:
  pre-production -> production -> post-production is a useful high-level
  compression for communication, but too coarse for implementation tracking.

client/server:
  Keep as responsibility split:
    client = HTML/CSS/JS/browser behavior
    server = CMS/runtime/data/rendering/auth

WordPress:
  Pilot template work is WordPress block-theme work, not generic frontend-app
  work.
  Interactivity should default to WordPress-native/progressive paths unless a
  plugin/runtime cycle accepts another dependency.
```

Decision Matrix addition:

```txt
source_section: PART 04 CH01-CH02
workflow_stage: implementation
decision_point: choose process model and runtime responsibility
matrix_fields_to_test:
  process_model
  runtime_layer
  WordPress_surface
  dependency_policy
  validation_gate
```

Axismundi application:

```txt
Pilot template implementation pass should be:
  pre-production: ontology + TT5 + Google Sites evidence
  production: Pilot template/pattern markup
  post-production: validation + human QA + routed follow-ons
```

---

## 7. Project Close / Deliverables

Section status:

```txt
detail_pass: 1
scope: user's rough notes only
source_snapshot: 2026-05-24
review_unit: Section 7 only
copyright_classification:
  user notes = user-authored digest
  external sources = linked professional / official references, paraphrased
  book body text = not imported
source_note:
  IEEE SWEBOK official page was located through search, but direct fetch was
  unavailable in this environment. Treat SWEBOK mapping as user-note evidence
  plus official-source pointer, not as fully rederived from fetched body text.
```

Sources:

- [SWEBOK, IEEE Computer Society](https://www.computer.org/education/bodies-of-knowledge/software-engineering)
- [Publishing Themes, WordPress Developer Handbook](https://developer.wordpress.org/themes/advanced-topics/publishing-themes/)
- [Writing Documentation, WordPress Theme Handbook](https://developer.wordpress.org/themes/releasing-your-theme/writing-documentation/)
- [Theme Structure, WordPress Developer Handbook](https://developer.wordpress.org/themes/core-concepts/theme-structure/)
- [Required Theme Review Items, Make WordPress Themes](https://make.wordpress.org/themes/handbook/review/required/)
- [Theme Review Process, Make WordPress Themes](https://make.wordpress.org/themes/handbook/review/)
- [About READMEs, GitHub Docs](https://docs.github.com/articles/about-readmes)

User note coverage:

```txt
SWEBOK:
  Covered user notes:
    requirements / architecture / design / construction / testing
    operation / maintenance / configuration management / engineering management
    process / models and methods / quality / security / professional practice
    economics / computing foundations / mathematical foundations /
    engineering foundations

deliverable types:
  Covered user notes:
    design files / source code / database / documented deliverables
    proposal / project plan / requirements definition
    design mockup / design document / test plan / final report
    deliverable list / source and technical documents / test results
    user manual / maintenance plan / meeting minutes / schedule

classification / preservation / disposal:
  Covered user notes:
    collect generated work
    classify content and data by type
    preserve important materials and data with backup
    dispose of unnecessary materials through a defined procedure
    classify by content type, data type, and metadata
    backup/restore, version control, access control, retention policy
    disposal record and disposal policy
    remove temporary files, unnecessary drafts, duplicates, outdated records,
      and irrelevant materials

project deliverables by phase:
  Covered user notes:
    planning: project plan
    design: design mockups / wireframes / UI/UX design
    development: code documentation / source / API docs
    testing: test plan / test result / scenarios / bugs
    deployment: deployment plan
    maintenance: maintenance plan

CBD deliverables:
  Covered user notes:
    analysis / design / implementation / testing
    25 required deliverables, with omission possible when work does not exist
    traceability and consistency across deliverables
    requirements, use cases, traceability matrix
    class/UI/component/interface/architecture/database designs
    test plans, scenarios, cases, results, acceptance artifacts
    program code, DB table, user/operator/install guides

final report / presentation / aftercare:
  Covered user notes:
    goal achievement / major outcomes / issues / future improvements
    final presentation: cover, TOC, overview, process, results, test evidence,
      analysis/evaluation, conclusion/future plan
    aftercare: maintenance plan, regular review/update, user feedback,
      documentation and records
```

Research fill:

```txt
A. SWEBOK and CBD as taxonomy, not mandate:
  SWEBOK and CBD are useful as deliverable taxonomies. They help name possible
  artifacts and traceability relationships.

  Axismundi guardrail:
    Do not require every SWEBOK knowledge area or every CBD deliverable in a
    Pilot, catalog, or distributable cycle. Select the smallest deliverable set
    that supports the current product boundary and evidence need.

B. Axismundi deliverable lifecycle:
  source:
    user-authored notes, source docs, code, assets, external references

  generated:
    published styleguide mirrors, validation reports, derived assets,
    release derivatives

  decision/phase records:
    Phase 0 plans, Phase 1 diagnostics, Phase 2 implementation docs,
    Phase 3 verification docs, Phase 5 close docs, handoff meta-docs

  release/package:
    distributable theme files, style.css headers, theme.json, templates,
    patterns, readme.txt, screenshot.png, license and notices

  archive/retention:
    git history, tagged release artifacts, source-of-authority records,
    SHA/hash evidence where needed

C. Preservation and disposal:
  Keep:
    source-of-authority files, authored corpus notes, cycle docs, validation
    evidence, license/notice records, release-critical assets, final package
    artifacts

  Restore or remove:
    unrelated generated churn, temporary files, stale generated module outputs,
    duplicate drafts, obsolete reports, local test artifacts, non-source mirrors
    outside the intended M7 route

  Axismundi examples:
    v3.6.22 and v3.6.23/24 restored unrelated generated artifacts.
    de106ab and 464604a are maintenance catchup commits outside the Lock 5
    self-application count chain.

D. WordPress release-readiness mapping:
  Publishing Themes gives a release sequence:
    required files -> testing -> Theme Review Guidelines -> documentation ->
    submission/review.

  Theme Review required items are a submission gate, not a design brainstorming
  source. They become active when the distributable skeleton and release package
  exist.

  readme.txt:
    WordPress theme documentation requires readme.txt for Theme Directory
    submission and uses it for end-user/documentation obligations.

  Theme structure:
    README.txt is a release artifact, not a runtime surface. It should be
    tracked separately from GitHub README.md and Phase 5 close docs.

E. GitHub / repository documentation:
  GitHub README is repository-facing documentation. It helps orient visitors,
  contributors, and future maintainers.

  Axismundi separation:
    README.md = repo/project orientation
    readme.txt = WordPress.org theme submission artifact
    Phase 5 close doc = cycle evidence and decision close
    NEXT-SESSION/CURRENT-STATE/ROADMAP/CHANGELOG = handoff meta-docs
    final presentation/report = portfolio or stakeholder-facing summary

F. Final report and presentation:
  User's final report structure maps well to Axismundi Phase 5 close docs:
    goals -> scope -> process -> results -> issues -> future improvements

  User's presentation structure maps to a later portfolio/release narrative:
    cover -> overview -> process -> results -> validation -> evaluation ->
    future plan

  Guardrail:
    A final report is not the same artifact as wp.org readme.txt, GitHub README,
    or Phase 5 close. They can share evidence but have different audiences.

G. Aftercare / maintenance:
  User notes map directly to Axismundi maintenance practice:
    maintenance plan -> routed forward list and next-cycle trigger
    regular review/update -> handoff catchup commits
    user feedback -> review verdicts and amend cycles
    documentation and records -> cycle docs and memory promotion

H. Axismundi documentation retention / disposal candidate policy:
  Purpose:
    Prevent documentation discipline from turning into uncontrolled
    documentation accumulation.

  Five-tier policy:
    keep:
      Source-of-authority files, Phase 5 close evidence, promoted memory files,
      release-critical docs, root handoff meta-docs, and cross-cutting authority
      docs. Keep means preserve in the working tree and git lineage.

    archive:
      Superseded but historically meaningful docs. Archive does not mean delete;
      it means the file remains accessible with deprecated/superseded status or
      an archive route.

    fold:
      Duplicate or overlapping framework material that should be merged into a
      stronger existing document or memory. Fold does not mean keep both copies
      forever; after consolidation, the redundant source should be removed or
      archived with explicit reason.

    restore_remove:
      Generated churn, temp reports, stale drafts, duplicate local notes, or
      files produced by tooling outside the intended scope. Restore/remove is a
      working-tree cleanup action, not a commit-and-revert pattern.

    route_forward:
      Material that is useful but not yet decided. Route it to BACKLOG, a
      Phase 5 routed-forward list, a memory watch item, or a future cycle entry.

  Axismundi documentation classes and default policies:
    cycle_docs:
      docs/v3.6.x Phase 0/1/2/3/5 docs -> keep

    cross_cutting_docs:
      ASSET-SURFACE-INDEX, LICENSE-MATRIX, BACKLOG, and similar authority docs
      -> keep

    root_handoff_meta_docs:
      CHANGELOG, CURRENT-STATE, NEXT-SESSION, ROADMAP -> keep and catch up
      through maintenance commits when stale

    corpus_source_material:
      corpus/source/* and rough research prep -> route_forward until Phase 1
      decides promote / fold / archive / keep

    atlas_core_ontology:
      promoted atlas/* and core/* knowledge -> keep

    decisions_layer:
      if introduced, decisions/* records -> keep, with separate layer decision

    generated_reports:
      validation reports, published mirrors outside intended scope, and tooling
      churn -> restore_remove unless explicitly retained as evidence

    memory_files:
      promoted memories -> keep; watch candidates -> route_forward; overlapping
      candidates -> fold

    release_artifacts:
      readme.txt, screenshot.png, release seal derivatives, license/notice
      package evidence -> keep once the release cycle activates them

    legacy_archive:
      superseded historical docs -> archive, not silent delete

  Triggers:
    keep trigger:
      cycle close, source-of-authority declaration, promoted memory, release
      package activation, or cross-cutting authority status

    archive trigger:
      explicit supersession, external source version refresh, or historical
      value without active decision authority

    fold trigger:
      overlapping memory candidates, duplicated framework docs, or section
      prep material consolidated into corpus/atlas/core

    restore_remove trigger:
      generated churn, stale drafts, duplicate notes, temp files, unrelated
      publish outputs, or validation artifacts not intended for the commit

    route_forward trigger:
      unresolved decision, useful prep material, future-cycle candidate, or
      evidence that needs user GO before promotion

  Current Axismundi examples:
    v3.6.22 and v3.6.23/24 generated artifact restores -> restore_remove
    M13 validator-anchor candidate folded into M9 -> fold
    M7 tracked-copy framework memory -> keep
    de106ab and 464604a handoff catchup commits -> keep via maintenance
    SKELETON.md / SKELETON-RESEARCH-FILL.md -> route_forward until Phase 1

  M7 / M9 cross-link:
    M7 controls generated mirror and tracked-copy cleanup discipline.
    M9 controls source-of-authority and validator-anchor retention discipline.
    This retention/disposal policy sits above both: it chooses keep, archive,
    fold, restore/remove, or route-forward by document class and trigger.
```

Decision Matrix addition:

```txt
source_section: PART 07
workflow_stage: handoff_report
decision_point: choose deliverable set and retention/reporting policy
matrix_fields_to_test:
  source_page_reference
  source_confidence
  copyright_classification
  deliverable_type
  deliverable_audience
  lifecycle_state
  source_or_generated
  retention_policy
  disposal_policy
  traceability_requirement
  release_relevance
  submission_relevance
  maintenance_relevance
  artifact_boundary

candidate_rows:
  Deliverable taxonomy decision:
    input: SWEBOK/CBD/user-note deliverable inventory
    output: selected Axismundi deliverable subset for current cycle
    Axismundi_route: core_ontology_now, distributable_later

  Documentation artifact boundary decision:
    input: repo docs / phase docs / readme.txt / README.md / final report needs
    output: audience-specific artifact map
    Axismundi_route: core_ontology_now, release_seal_later

  Retention/disposal decision:
    input: source/generated/temp/release artifact classification
    output: keep / restore / remove / archive policy
    Axismundi_route: M7_M9_application

  WordPress submission readiness decision:
    input: required files + review guidelines + documentation obligations
    output: wp.org submission checklist, only after distributable exists
    Axismundi_route: distributable_later, wp_org_later

  Maintenance handoff decision:
    input: routed forward list + stale handoff docs + user feedback
    output: maintenance commit vs next-cycle route vs memory promotion
    Axismundi_route: maintenance_or_phase5

  Documentation retention/disposal decision:
    input: document class + source authority + lifecycle state + cycle scope
    output: keep / archive / fold / restore_remove / route_forward
    sub_rows:
      cycle docs retention:
        default: keep
      root handoff catchup:
        default: keep through maintenance commit
      corpus research prep:
        default: route_forward until Phase 1 promotes, folds, archives, or keeps
      memory lifecycle:
        default: keep promoted / route_forward watch / fold overlapping
      generated artifacts:
        default: restore_remove unless explicit evidence retention
      legacy docs:
        default: archive when superseded, not silent delete
      release derivatives:
        default: keep only after release-seal or distributable cycle activation
    Axismundi_route: M7_M9_application, M14_consideration,
      retention_policy_now
```

Axismundi application:

```txt
Recommended future order:
  1. Section 7 defines deliverable taxonomy and artifact boundaries.
  2. v3.6.25 Phase 1 reconciles Decision Matrix fields across sections.
  3. TT5 audit uses Section 7 to classify TT5 docs/code/release artifacts.
  4. Pilot template pass uses Section 7 to decide which outputs are kept as
     evidence, which are generated, and which are disposable.
  5. Distributable skeleton activates the WordPress release-readiness subset.
  6. Release seal / wp.org cycles activate readme.txt, screenshot, license,
     final package, and submission evidence.

Section 4-5 cross-link:
  Section 4 storyboard fields feed Section 7 handoff artifacts.
  Section 5 Honeycomb/Nielsen/WCAG gates feed Section 7 test/result evidence.
  Section 5 phase mapping aligns with Section 7 final report / Phase 5 close.

Anti-collapse rules:
  SWEBOK enumeration is taxonomy, not an implementation requirement.
  CBD 25 deliverables are reference material, not Pilot/distributable mandates.
  Final report, Phase 5 close, GitHub README, and wp.org readme.txt are separate
  artifacts with different audiences and gates.
  Theme Review requirements become active only when a distributable submission
  package exists.
  Archive is not silent deletion.
  Fold is not duplicate preservation.
  Restore/remove is not commit-and-revert.
  Keep without a retention rule creates uncontrolled accumulation.
  Documentation retention/disposal policy applies cross-cutting, not only to
  release deliverables.
```

Opus review checklist:

```txt
1. User-note coverage:
   Verify SWEBOK/CBD/deliverable types/documented deliverables/preservation/
   disposal/phase deliverables/final report/presentation/aftercare coverage.

2. Source validity:
   Confirm source roles:
     IEEE SWEBOK = official taxonomy pointer, fetch-limited in this environment
     WordPress Developer Handbook = release/documentation/theme structure
     Make WordPress Themes = review requirements and review process
     GitHub Docs = repository README audience

3. Axismundi relevance:
   Confirm Section 7 supports release seal, wp.org readiness, maintenance
   commits, handoff docs, and future distributable work without creating any of
   those artifacts now.

4. Anti-collapse guardrail:
   Confirm deliverable taxonomy, release-readiness gates, repository docs,
   WordPress submission docs, and cycle evidence remain separate.

5. Matrix usefulness:
   Confirm fields are useful for v3.6.25 Phase 1 schema testing and connect to
   Section 4-5 without overfitting to release work.
```

---

## Phase 1 Use

Recommended Phase 1 handling:

```txt
1. Use `wdd.md` as the user-authored source.
2. Use `SKELETON.md` as the organized digest.
3. Use this file only as evidence fill for rough user-note areas.
4. Prefer English professional / official sources for corroboration.
5. Do not fill untouched book sections unless the user adds notes or asks for
   a later pass.
6. Test the Decision Matrix schema against these user-note areas first.
```

Candidate fields to add to Phase 1 schema:

```txt
user_note_present: yes/no
research_fill_present: yes/no
source_confidence: user_note / official_source / needs_verification
copyright_classification: user-authored / paraphrase / structural-reference
```
