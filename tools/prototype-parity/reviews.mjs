import { clean } from './clean.mjs';

export function exportReviews({ app, ix }) {
    return app.allReviews().map((review) => ({
        reviewId: review.id,
        reviewTrust: clean(ix.reviewTrust(review)),
        weight: app.reviewWeight(review),
    }));
}
