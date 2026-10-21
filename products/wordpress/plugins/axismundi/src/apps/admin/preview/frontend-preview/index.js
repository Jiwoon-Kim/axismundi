import { getAxismundiConfig } from '../../../../shared/runtime/config';

export default function FrontendPreview( { label } ) {
	if ( ! getAxismundiConfig().stylebookEnabled ) {
		return <p>Stylebook is disabled.</p>;
	}
	return (
		<section className="ax-admin-frontend-preview" aria-label="Frontend preview">
			<p className="ax-admin-frontend-preview__label">{ label } preview</p>
			<iframe
				className="ax-admin-frontend-preview__frame"
				title={ `Axismundi Social ${ label } preview` }
				src={ getAxismundiConfig().stylebookUrl }
			/>
		</section>
	);
}
